<?php

declare(strict_types=1);

namespace App\Service\AdSlot;

use App\Library\HttpClient\HttpClient;
use App\Model\AppCompany;
use App\Utils\Log;

class AdSlotYlhService extends AdSlotMediaAbstract
{
    const PLATFORM = 'ylh';

    const AD_SLOT_TYPE_NATIVE = 'FLOW_NORMAL_0';//代码位类型 - 信息流
    const AD_SLOT_TYPE_INTER = 'INSERTION_INSERTION';//代码位类型 - 插屏
    const AD_SLOT_TYPE_INTER_FULL = 'INSERTION_INSERTION';//代码位类型 - 全屏插屏
    const AD_SLOT_TYPE_SPLASH = 'FLASH_OTHER';//代码位类型 - 开屏
    const AD_SLOT_TYPE_REWORD_VIDEO = 'REWARDED_APP_OTHER';//代码位类型 - 激励视频

    public function refreshApp($account)
    {
        $accountId = data_get($account, 'company_id');
        $mediaConf = data_get($account, 'media_conf_ylh');
        if(!$mediaConf){
            return;
        }
        $appList = $this->requestAppList($mediaConf);
        $this->saveMediaApp($appList, self::PLATFORM, $accountId);
    }

    protected function requestAppList($mediaConf)
    {
        $appList = [];
        $page = 0;
        do{
            $page++;
            $res = HttpClient::connect('Ylh', 'Medium')->setConf($mediaConf)->list(['page' => $page]);
            $totalPage = data_get($res, 'data.page_info.total_page');
            foreach (data_get($res, 'data.list') as $row) {
                $appList[] = [
                    'app_id' => data_get($row, 'app_id'),
                    'app_name' => data_get($row, 'medium_name'),
                    'package_name' => data_get($row, 'package_name'),
                    'status' => in_array($row['status'], ['Approved']) ? 1 : 0
                ];
            }
        }while($page < $totalPage);
        return $appList;
    }

    public function createAdSlot($accountId, $mediaAppId, $data, $execKey, $id = 0)
    {
        $conf = AppCompany::select('media_conf_ylh')->where('company_id', $accountId)->first();
        $mediaConf = data_get($conf, 'media_conf_ylh');
        foreach ($data as $item) {
            $model = $this->getAppMediaAdModel($accountId, $mediaAppId, self::PLATFORM, $item, $execKey, $id);
            $requestParams = [
                'app_id' => intval($mediaAppId),
                'placement_name' => $model->ad_name,
            ];
            if($model->is_bidding == 1){
                $requestParams['price_strategy_type'] = 'BiddingPrice';
                $requestParams['real_time_bidding_type'] = 'Client_Bidding';
            }elseif($model->price && $model->price > 0){
                $requestParams['price_strategy_type'] = 'TargetPrice';
                $requestParams['ecpm_price'] = intval($model->price * 100);
            }
            $adTypeParams = $this->getAdTypeParams($model->ad_type_key);
            $requestParams = array_merge($requestParams, $adTypeParams);
            try {
                $res = HttpClient::connect('Ylh', 'Placement')->setConf($mediaConf)->add($requestParams);
            }catch (\Exception $exception){
                Log::system('error', 'ylh创建失败:调用接口错误', [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                    'execKey' => $execKey
                ]);
                $model->remark = '创建失败:调用接口错误';
                $model->save();
                continue;
            }
            if (data_get($res, 'code') != 0) {
                $model->remark = '创建失败' . data_get($res, 'message');
                $model->save();
                continue;
            }
            $model->ad_id = data_get($res, 'data.placement_id');
            $model->is_create = 1;
            $model->save();
        }
    }

    /**
     * 创建信息流
     */
    protected function native(): array
    {
        return [
            'custom_position_scene_level3' => self::AD_SLOT_TYPE_NATIVE,
            'ad_pull_mode' => 'SDK',
            'render_type' => 'TEMPLATE',
            'ad_crt_type_list' => ['VIDEO', 'IMAGE'],
            'ad_crt_template_type' => ['TP_BT', 'TT_BP'],
        ];
    }

    /**
     * 创建插屏
     */
    protected function inter(): array
    {
        return [
            'custom_position_scene_level3' => self::AD_SLOT_TYPE_INTER,
            'ad_pull_mode' => 'SDK',
            'render_type' => 'TEMPLATE',
            'ad_crt_type_list' => ['VIDEO', 'IMAGE'],
            'ad_crt_template_type' => ['INLINE_VH'],
        ];
    }

    protected function fullVideo(): array
    {
        return $this->interFull();
    }

    /**
     * 创建全屏插屏
     */
    protected function interFull(): array
    {
        return [
            'custom_position_scene_level3' => self::AD_SLOT_TYPE_INTER_FULL,
            'ad_pull_mode' => 'SDK',
            'render_type' => 'TEMPLATE',
            'ad_crt_type_list' => ['VIDEO', 'IMAGE'],
            'ad_crt_template_type' => ['INLINE_FULL'],
        ];
    }

    /**
     * 创建开屏
     */
    protected function splash(): array
    {
        return [
            'custom_position_scene_level3' => self::AD_SLOT_TYPE_SPLASH,
            'ad_pull_mode' => 'SDK',
            'flash_crt_type' => 'FLASH',
            'ad_crt_type_list' => ['FLASH_IMAGE_V', 'FLASH_IMAGE_H', 'FLASH_VIDEO_V', 'FLASH_VIDEO_H'],
        ];
    }

    /**
     * 创建激励视频
     */
    protected function rewordVideo(): array
    {
        return [
            'custom_position_scene_level3' => self::AD_SLOT_TYPE_REWORD_VIDEO,
            'ad_pull_mode' => 'SDK',
            'rewarded_video_crt_type' => 'VIDEO',
        ];
    }
}