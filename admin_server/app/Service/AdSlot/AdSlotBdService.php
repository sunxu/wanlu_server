<?php

declare(strict_types=1);

namespace App\Service\AdSlot;

use App\Library\HttpClient\HttpClient;
use App\Model\AppCompany;
use App\Utils\Log;

class AdSlotBdService extends AdSlotMediaAbstract
{
    const PLATFORM = 'bd';

    public function refreshApp($account)
    {
        // TODO: Implement refreshApp() method.
    }

    protected function requestAppList($mediaConf)
    {
        // TODO: Implement requestAppList() method.
    }

    public function createAdSlot($accountId, $mediaAppId, $data, $execKey, $id = 0)
    {
        $conf = AppCompany::select('media_conf_bd')->where('company_id', $accountId)->first();
        $mediaConf = data_get($conf, 'media_conf_bd');
        foreach ($data as $item) {
            $model = $this->getAppMediaAdModel($accountId, $mediaAppId, self::PLATFORM, $item, $execKey, $id);
            $requestParams = [
                'ad_name' => $model->ad_name,
                'app_sid' => strval($mediaAppId),
            ];
            $requestParams['price_type'] = 1;
            if($model->is_bidding == 1){
                $requestParams['price_type'] = 2;//bidding
            } else if ($model->price && $model->price > 0) {
                $requestParams['cpm'] = floatval($model->price);
            }

            $adTypeParams = $this->getAdTypeParams($model->ad_type_key);
            $requestParams = array_merge($requestParams, $adTypeParams);
            try {
                $res = HttpClient::connect('Ubapi', 'Position')->setConf($mediaConf)->add($requestParams);
            }catch (\Exception $exception) {
                Log::system('error', 'bd创建失败:调用接口错误', [
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
            $model->ad_id = data_get($res, 'data.tu_id');
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
            'ad_type' => 36,// 代码位类型
            'ad_info' => [
                'info_flow_style_control_type' => 1,
                'info_flow_ad_num' => 1,
                'info_flow_material_types' => [1,7],
                'info_flow_templates' => [28, 14, 18, 19, 20, 16, 17, 30]
            ]
        ];
    }

    /**
     * 创建插屏
     */
    protected function inter(): array
    {
        return [
            'ad_type' => 34,// 代码位类型
            'ad_info' => [
                'interstitial_ad_scene' => 1,
                'interstitial_style_types' => [1, 2, 5],
                'interstitial_material_types' => [1,7]
            ],
            'scene_tag' => 5
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
            'ad_type' => 34,// 代码位类型
            'ad_info' => [
                'interstitial_ad_scene' => 1,
                'interstitial_style_types' => [4],
                'interstitial_material_types' => [1,7]
            ],
            'scene_tag' => 5
        ];
    }

    /**
     * 创建开屏
     */
    protected function splash(): array
    {
        return [
            'ad_type' => 33,// 代码位类型
            'ad_info' => [
                'splash_material_types' => [1, 7],
                'splash_show_control' => 2
            ],
            'scene_tag' => 3
        ];
    }

    /**
     * 创建激励视频
     */
    protected function rewordVideo(): array
    {
        return [
            'ad_type' => 44,// 代码位类型
            'ad_info' => [

            ]
        ];
    }
}