<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Constants\CsjConst;
use App\Library\HttpClient\HttpClient;
use App\Model\AppCompany;
use App\Model\AppMediaAd;
use App\Model\PlacementCsjNetwork;
use App\Service\Placement\LaunchBaseService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Utils\WaitGroup;

/**
 * @Command
 */
#[Command]
class BindGm extends HyperfCommand
{
    protected $name = 'temp:bindGm';

    const BIND_PRICE_OFFSET = [
        'ks' => 0.1,
        'ylh' => 0.2,
        'bd' => 0.3,
        'csj' => 0.4,
    ];

    public function configure()
    {
        parent::configure();
        $this->setDescription('日报统计');
    }

    public function handle()
    {
        $execKey = '17c98ca4-ad16-4033-be75-e830e85e17a2';
        $adSlotList = AppMediaAd::where('exec_key', $execKey)
            ->where('is_create', 1)
            ->where('gmid', '<>', '')
            ->get()->all();
        $accountId = 117;
        $conf = AppCompany::select('media_conf_csj')->where('company_id', $accountId)->first();
        $mediaConf = data_get($conf, 'media_conf_csj');
        $wg = new WaitGroup();
        $gmAdSlots = collect($adSlotList)->groupBy('gmid')->toArray();
        foreach ($gmAdSlots as $gmId => $adSlots){
            // 先创建穿山甲
            $csjAdSlots = collect($adSlots)->where('platform', 'csj')->all();
            $this->bindAdSlotFunc($gmId, $csjAdSlots, $mediaConf, $execKey);
            $otherAdSlots = collect($adSlots)->where('platform','<>', 'csj')->all();
            $wg->add();
            go(function () use($gmId, $otherAdSlots, $mediaConf, $wg, $execKey) {
                $this->bindAdSlotFunc($gmId, $otherAdSlots, $mediaConf, $execKey);
                $wg->done();
            });
        }
        $wg->wait();
//        make(LaunchBaseService::class)->schedule();
    }

    protected function bindAdSlotFunc($gmId, $adSlots, $mediaConf, $execKey)
    {
        try {
            $requestParams = $this->formatBindData($gmId, $adSlots);
            $res = HttpClient::connect('Csj', 'Waterfall')->setConf($mediaConf)->batchAdslot($requestParams);
            $ids = collect($adSlots)->pluck('id')->toArray();
            if($res['code'] == 100){
                AppMediaAd::whereIn('id', $ids)->update(['is_bind' => 1]);
            }else{
                AppMediaAd::whereIn('id', $ids)->update(['remark' => "绑定失败". $res['message']]);
            }
        }catch (\Exception $exception) {
            AppMediaAd::whereIn('id', $ids)->update(['remark' => "绑定失败:接口调用失败"]);
        }
    }

    protected function formatBindData($gmId, $adSlots)
    {
        $adSlotList = [];
        foreach ($adSlots as $adSlot) {
            $adSlotInfo = [
                'ad_slot_id' => strval(data_get($adSlot, 'ad_id')),
                'ad_slot_name' => data_get($adSlot, 'ad_name'),
                'bidding_type' => 0,
                'status' => CsjConst::STATUS_OPEN,
                'origin_type' => $this->getOriginType($adSlot),
                'network' => $this->getNetwork($adSlot),
            ];
            // 设置代码位类型
            if (data_get($adSlot, 'platform') != 'csj') {
                if (in_array(data_get($adSlot, 'ad_type_key'), ['full_video', 'inter_full'])) {
                    $adSlotInfo['sub_ad_type'] = CsjConst::SUB_AD_TYPE_FULL;
                }
                if (data_get($adSlot, 'ad_type_key') == 'inter'){
                    $adSlotInfo['sub_ad_type'] = CsjConst::SUB_AD_TYPE_INTER;
                }
            }
            if (data_get($adSlot, 'platform') == 'bd') {
                $adSlotInfo['sub_ad_type'] = CsjConst::SUB_AD_TYPE_INTER;
            }
            // 设置bidding
            if(data_get($adSlot, 'is_bidding') == 1) {
                if (data_get($adSlot, 'platform') == 'csj') {
                    $adSlotInfo['bidding_type'] = 1;//服务端竞价
                }else{
                    $adSlotInfo['bidding_type'] = 2;//客户端竞价
                }
            }
            // 非bidding 设置价格 价格排序
            if(data_get($adSlot, 'is_bidding') != 1) {
                $adSlotInfo['sort_type'] = CsjConst::SORT_TYPE_PRICE;
                $adSlotInfo['price'] = floatval(1);
                if (data_get($adSlot, 'price') > 0) {
                    $adSlotInfo['price'] = floatval(data_get($adSlot, 'price'));
                }
                $offset = self::BIND_PRICE_OFFSET[data_get($adSlot, 'platform')]?:0;
                $adSlotInfo['price'] += $offset;
            }
            $adSlotList[] = $adSlotInfo;
        }
        return [
            'ad_unit_id' => intval($gmId),
            'ad_slot_list' => json_encode($adSlotList, JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES)
        ];
    }

    protected function getNetwork($adSlot)
    {
        // 优量汇广告网络
        if(strtolower(data_get($adSlot, 'platform')) == 'ylh' && data_get($adSlot, 'is_bidding') == 1) {
            $definedNetworks = PlacementCsjNetwork::where('company_id', data_get($adSlot, 'account_id'))
                ->where('network_name', CsjConst::YLH_NETWORK_DEFINE)->first();
            return  data_get($definedNetworks, 'network_id');
        }
        return CsjConst::NETWORK_MAP[data_get($adSlot, 'platform')]??0;
    }

    /**
     * 获取渲染类型
     * @param $adSlot
     * @return int
     */
    protected function getOriginType($adSlot)
    {
        $adType = data_get($adSlot, 'ad_type_key');
        $platform = data_get($adSlot, 'platform');
        $isBidding = data_get($adSlot, 'is_bidding');

        $isFull = in_array($adType, ['full_video', 'inter_full']);//全屏
        $isInter = $adType == 'inter';//插屏
        $isNative = $adType == 'native';//信息流
        $isSplash = $adType == 'splash';//开屏
        $isBidding = $isBidding == 1;
        $isYlh = $platform == 'ylh';
        $isKs = $platform == 'ks';
        $isCsj = $platform == 'csj';
        $isBd = $platform == 'bd';

        if(
            (($isFull || $isInter || $isNative) && $isYlh && $isBidding) ||
            (($isInter || $isNative) && $isKs) ||
            (($isFull || $isInter || $isNative) && $isBd) ||
            (($isFull || $isInter || $isNative) && $isCsj)
        ) {
            return CsjConst::ORIGIN_TYPE_TEMPLATE_1;
        }
        if(
            ($isSplash && $isCsj)
        ) {
            return CsjConst::ORIGIN_TYPE_SELF;
        }
        if(
            (($isFull || $isInter || $isNative) && $isYlh && !$isBidding)
        ) {
            return CsjConst::ORIGIN_TYPE_TEMPLATE_2;
        }
        return CsjConst::ORIGIN_TYPE_NONE;
    }
}
