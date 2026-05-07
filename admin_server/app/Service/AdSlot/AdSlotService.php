<?php

declare(strict_types=1);

namespace App\Service\AdSlot;

use App\Constants\AppConst;
use App\Constants\CsjConst;
use App\Library\Excel\ReaderExcel;
use App\Library\HttpClient\HttpClient;
use App\Model\AppCompany;
use App\Model\AppMediaAd;
use App\Model\AppMediaApp;
use App\Model\PlacementCsjNetwork;
use App\Utils\Log;
use App\Utils\RedisManager;
use Hyperf\Utils\WaitGroup;

class AdSlotService
{
    const SERVICE_MAP = [
        'ks' => AdSlotKsService::class,
        'ylh' => AdSlotYlhService::class,
        'csj' => AdSlotCsjService::class,
        'bd' => AdSlotBdService::class,
    ];

    /**
     * 绑定代码位 价格排序值
     */
    const BIND_PRICE_OFFSET = [
        'ks' => 0.1,
        'ylh' => 0.2,
        'bd' => 0.3,
        'csj' => 0.4,
    ];

    /**
     * 获取创建绑定执行状态
     * @param string $execKey
     * @return array
     */
    public function getCreateBindAdSlotStatus(string $execKey)
    {
        $redis = RedisManager::get();
        $status = $redis->get("createBindAdSlot:{$execKey}");
        $status = json_decode($status?:'[]');
        return [
            'status' => data_get($status, 'status'),
            'message' => data_get($status, 'message'),
        ];
    }

    /**
     * 创建绑定广告代码位
     * @param string $execKey
     * @param string $filePath
     * @return string
     */
    public function createBindAdSlot(string $execKey, string $filePath)
    {
        $redis = RedisManager::get();
        $redis->set("createBindAdSlot:{$execKey}", json_encode(['status' => 'doing']));
        go(function () use($filePath, $execKey, $redis) {
            defer(function () use($execKey, $redis) {
                $redis->expire("createBindAdSlot:{$execKey}", 60 * 60);
            });
            // 读取excel
            try {
                $redis->set("createBindAdSlot:{$execKey}", json_encode(['status' => 'doing', 'message' => '读取excel']));
                $data = $this->readExcel($filePath);
            }catch (\Exception $exception) {
                Log::system('error', "读取excel失败", [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                    'execKey' => $execKey
                ]);
                $redis->set("createBindAdSlot:{$execKey}", json_encode(['status' => 'fail', 'message' => '读取excel失败:'.$exception->getMessage()]));
                return;
            }
            // 拉取app
            try {
                $redis->set("createBindAdSlot:{$execKey}", json_encode(['status' => 'doing', 'message' => '拉取APP']));
                $mediaAppIds = collect($data)->keyBy('media_app_id')->keys()->all();
                $mediaAppsInfo = $this->getAppMediaInfo($mediaAppIds);
            }catch (\Exception $exception) {
                Log::system('error', "拉取APP失败", [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                    'execKey' => $execKey
                ]);
                $redis->set("createBindAdSlot:{$execKey}", json_encode(['status' => 'fail', 'message' => '拉取APP失败:'.$exception->getMessage()]));
                return;
            }
            // 创建 绑定
            try {
                $redis->set("createBindAdSlot:{$execKey}", json_encode(['status' => 'doing', 'message' => '创建绑定']));
                $mediaAppsInfo = collect($mediaAppsInfo)->groupBy('platform')->all();
                $adSlotData = collect($data)->groupBy('media_app_id')->all();
                $this->createAdSlot($adSlotData, $mediaAppsInfo, $execKey);
                $this->bindAdSlot($execKey);
            }catch (\Exception $exception) {
                Log::system('error', "创建绑定失败", [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                    'execKey' => $execKey
                ]);
                $redis->set("createBindAdSlot:{$execKey}", json_encode(['status' => 'fail', 'message' => '创建绑定失败:'.$exception->getMessage()]));
                return;
            }
            $redis->set("createBindAdSlot:{$execKey}", json_encode(['status' => 'success']));
        });
        return $execKey;
    }

    /**
     * 读取excel的内容
     * @param string $filePath
     * @return mixed[]
     */
    protected function readExcel(string $filePath)
    {
        $excel = make(ReaderExcel::class, [$filePath]);
        $excel->setMaxColumn(5);
        $excel->setReplaceColumn([
            'A' => 'gmid',
            'B' => 'media_app_id',
            'C' => 'ad_type',
            'D' => 'price',
            'E' => 'is_bidding'
        ]);
        $data = $excel->readExcel();
        return collect($data)->map(function ($item) {
            switch (data_get($item, 'ad_type')){
                case '信息流':
                    $typeKey = AppConst::AD_TYPE_NATIVE;
                    break;
                case '插屏':
                    $typeKey = AppConst::AD_TYPE_INTER;
                    break;
                case '全屏插屏':
                    $typeKey = AppConst::AD_TYPE_INTER_FULL;
                    break;
                case '全屏':
                    $typeKey = AppConst::AD_TYPE_FULL_VIDEO;
                    break;
                case '开屏':
                    $typeKey = AppConst::AD_TYPE_SPLASH;
                    break;
                case '激励视频':
                    $typeKey = AppConst::AD_TYPE_REWORD_VIDEO;
                    break;
                default:
                    $typeKey = '';
            }
            data_set($item, 'ad_type_key', $typeKey);
            $media_app_id = data_get($item, 'media_app_id');
            data_set($item, 'media_app_id', trim((string)$media_app_id));
            return $item;
        })->toArray();
    }

    /**
     * 获取app信息
     * @param array $mediaAppIds
     * @return array
     */
    protected function getAppMediaInfo(array $mediaAppIds)
    {
        $mediaAppList = AppMediaApp::whereIn('app_id', $mediaAppIds)
            ->select(['account_id', 'app_id', 'platform'])->get()->all();
        // 未缓存的app
        $mediaAppIdsUnset = collect($mediaAppIds)
            ->diff(collect($mediaAppList)->pluck('app_id')->all())
            ->all();
        if (!$mediaAppIdsUnset) {
            return $mediaAppList;
        }
        // 存在未缓存的app 刷新app
        $companyLIst = AppCompany::select(['company_id', 'media_conf_csj', 'media_conf_ylh', 'media_conf_ks'])
            ->where('status', 1)->get()->all();
        $this->refreshApp($companyLIst);
        return AppMediaApp::whereIn('app_id', $mediaAppIds)
            ->select(['account_id', 'app_id', 'platform'])->get()->all();

    }

    /**
     * 从广告放拉去app信息
     * @param $accountList
     * @return void
     */
    public function refreshApp($accountList)
    {
        $wg = new WaitGroup();
        $wg->add(3);
        $this->refreshAppCoroutine($wg, $accountList, 'ylh');
        $this->refreshAppCoroutine($wg, $accountList, 'ks');
        $this->refreshAppCoroutine($wg, $accountList, 'csj');
        $wg->wait();
    }

    /**
     * 从广告放拉去app信息 协程
     * @param $wg
     * @param $accountList
     * @param $platform
     * @return void
     */
    protected function refreshAppCoroutine($wg, $accountList, $platform)
    {
        go(function () use($accountList, $wg, $platform) {
            try{
                foreach ($accountList as $account) {
                    make(self::SERVICE_MAP[$platform])->refreshApp($account);
                }
            }catch (\Exception $exception){
                Log::system('error', "{$platform}拉取app失败", [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ]);
            }
            $wg->done();
        });
    }

    /**
     * 创建广告位
     * @param $adSlotData
     * @param $mediaAppsInfo
     * @param $execKey
     * @return void
     */
    protected function createAdSlot($adSlotData, $mediaAppsInfo, $execKey)
    {
        $wg = new WaitGroup();
        $wg->add(4);
        $this->createAdSlotCoroutine($wg, $adSlotData, $mediaAppsInfo, $execKey, 'ylh');
        $this->createAdSlotCoroutine($wg, $adSlotData, $mediaAppsInfo, $execKey, 'ks');
        $this->createAdSlotCoroutine($wg, $adSlotData, $mediaAppsInfo, $execKey, 'csj');
        $this->createAdSlotCoroutine($wg, $adSlotData, $mediaAppsInfo, $execKey, 'bd');
        $wg->wait();
    }

    /**
     * 创建广告位 协程
     * @param $wg
     * @param $adSlotData
     * @param $mediaAppsInfo
     * @param $execKey
     * @param $platform
     * @return void
     */
    protected function createAdSlotCoroutine($wg, $adSlotData, $mediaAppsInfo, $execKey, $platform)
    {
        go(function () use ($wg, $adSlotData, $mediaAppsInfo, $execKey, $platform) {
            try{
                foreach (data_get($mediaAppsInfo, $platform) as $mediaApp) {
                    $mediaAppId = data_get($mediaApp, 'app_id');
                    $accountId = data_get($mediaApp, 'account_id');
                    $data = data_get($adSlotData, $mediaAppId);
                    make(self::SERVICE_MAP[$platform])->createAdSlot($accountId, $mediaAppId, $data, $execKey);
                }
            }catch (\Exception $exception){
                Log::system('error', "{$platform}创建失败", [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                    'execKey' => $execKey
                ]);
            }
            $wg->done();
        });
    }

    /**
     * 绑定代码位
     * @param $execKey
     * @return void
     */
    protected function bindAdSlot($execKey)
    {
        $adSlotList = AppMediaAd::where('exec_key', $execKey)
            ->where('is_create', 1)
            ->where('gmid', '<>', '')
            ->get()->all();
        $adSlotCsj = collect($adSlotList)->where('platform', 'csj')->first();
        $adSlotYlh = collect($adSlotList)->where('platform', 'ylh')->first();
        $accountId = data_get($adSlotYlh, 'account_id');
        $conf = AppCompany::select('media_conf_csj')->where('company_id', $accountId)->first();
        $mediaConf = data_get($conf, 'media_conf_csj');
        $wg = new WaitGroup();
        $gmAdSlots = collect($adSlotList)->groupBy('gmid')->toArray();
        foreach ($gmAdSlots as $gmId => $adSlots){
            // 先绑定穿山甲
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
    }

    /**
     * 绑定代码位 协程
     * @param $gmId
     * @param $adSlots
     * @param $mediaConf
     * @param $execKey
     * @return void
     */
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
            Log::system('error', '绑定失败', [
                'gmid' => $gmId,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'execKey' => $execKey
            ]);
        }
    }

    /**
     * 格式绑定代码位数据
     * @param $gmId
     * @param $adSlots
     * @return array
     */
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

    /**
     * 获取对应广告网络
     * @param $adSlot
     * @return array|int|mixed
     */
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
            (($isFull || $isInter || $isNative) && $isYlh) ||
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
//        if(
//            (($isFull || $isInter || $isNative) && $isYlh && !$isBidding)
//        ) {
//            return CsjConst::ORIGIN_TYPE_TEMPLATE_2;
//        }
        return CsjConst::ORIGIN_TYPE_NONE;
    }

    /**
     * 创建绑定广告代码位结果列表
     * @param string $execKey
     * @return array
     */
    public function createBindList(string $execKey)
    {
        $redis = RedisManager::get();
        $redis->del("createBindAdSlot:{$execKey}");
        return AppMediaAd::where('exec_key', $execKey)
            ->orderBy('platform')
            ->orderByDesc('id')
            ->get()->all();
    }

    /**
     * 创建广告位 重试
     * @param int $id
     * @return void
     */
    public function createRetry(int $id)
    {
        $adInfo = AppMediaAd::find($id);
        $platform = data_get($adInfo, 'platform');
        $accountId = data_get($adInfo, 'account_id');
        $mediaAppId = data_get($adInfo, 'media_app_id');
        $execKey = data_get($adInfo, 'exec_key');
        try{
            make(self::SERVICE_MAP[$platform])->createAdSlot($accountId, $mediaAppId, [$adInfo], $execKey, $id);
        }catch (\Exception $exception){
            Log::system('error', "{$platform}创建失败", [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'execKey' => $execKey
            ]);
        }
    }

    /**
     * 绑定代码位重试
     * @param int $id
     * @return void
     */
    public function bindRetry(int $id)
    {
        $adInfo = AppMediaAd::find($id);
        $gmId = data_get($adInfo, 'gmid');
        $adSlots = [object_to_arr($adInfo)];
        $execKey = data_get($adInfo, 'exec_key');
        $adSlotCsj = AppMediaAd::where('exec_key', $execKey)->where('platform', 'csj')->first();
        $adSlotYlh = AppMediaAd::where('exec_key', $execKey)->where('platform', 'ylh')->first();
        $accountId = data_get($adSlotYlh, 'account_id');
        $conf = AppCompany::select('media_conf_csj')->where('company_id', $accountId)->first();
        $mediaConf = data_get($conf, 'media_conf_csj');
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
            Log::system('error', '绑定失败', [
                'gmid' => $gmId,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'execKey' => $execKey
            ]);
        }
    }
}