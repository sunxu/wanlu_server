<?php

declare(strict_types=1);

namespace App\Service\AdSlot;

use App\Library\HttpClient\HttpClient;
use App\Model\AppCompany;
use App\Utils\Log;

class AdSlotKsService extends AdSlotMediaAbstract
{
    const PLATFORM = 'ks';

    const AD_SLOT_TYPE_NATIVE = 1;//代码位类型 - 信息流
    const AD_SLOT_TYPE_INTER = 13;//代码位类型 - 插屏
    const AD_SLOT_TYPE_FULL_VIDEO = 3;//代码位类型 - 全屏视频
    const AD_SLOT_TYPE_SPLASH = 4;//代码位类型 - 开屏
    const AD_SLOT_TYPE_REWORD_VIDEO = 2;//代码位类型 - 激励视频

    protected function requestAppList($mediaConf)
    {
        $res = HttpClient::connect('Ks', 'App')->setConf($mediaConf)->list();
        $appList = [];
        foreach (data_get($res, 'data') as $row) {
            $appList[] = [
                'app_id' => data_get($row, 'app_id'),
                'app_name' => data_get($row, 'name'),
                'package_name' => data_get($row, 'package_name'),
                'status' => in_array($row['audit_status'], [3]) ? 1 : 0//3和8是正常状态 其他是停用状态
            ];
        }
        return $appList;
    }

    public function refreshApp($account)
    {
        $accountId = data_get($account, 'company_id');
        $mediaConf = data_get($account, 'media_conf_ks');
        if(!$mediaConf){
            return;
        }
        $appList = $this->requestAppList($mediaConf);
        $this->saveMediaApp($appList, 'ks', $accountId);
    }

    public function createAdSlot($accountId, $mediaAppId, $data, $execKey, $id = 0)
    {
        $conf = AppCompany::select('media_conf_ks')->where('company_id', $accountId)->first();
        $mediaConf = data_get($conf, 'media_conf_ks');
        $timestampNonce = time();
        foreach ($data as $item) {
            $model = $this->getAppMediaAdModel($accountId, $mediaAppId, self::PLATFORM, $item, $execKey, $id);
            $requestParams = [
                'appId' => $mediaAppId,
                'name' => $model->ad_name,
            ];
            if ($model->is_bidding != 1) {
                $requestParams['priceStrategy'] = 1;
            } else {
                $requestParams['priceStrategy'] = 2;
            }
            $adTypeParams = $this->getAdTypeParams($model->ad_type_key);
            $requestParams = array_merge($requestParams, $adTypeParams);
            try {
                $timestampNonce++;
                $res = HttpClient::connect('Ks', 'Position')->setTImestamp($timestampNonce)->setConf($mediaConf)->add($requestParams);
            }catch (\Exception $exception){
                Log::system('error', 'ks创建失败:调用接口错误', [
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
            if (data_get($res, 'result') != 1) {
                $model->remark = '创建失败' . data_get($res, 'error_msg');
                $model->save();
                continue;
            }
            $model->ad_id = data_get($res, 'data');
            $model->save();
            if($model->price && $model->price > 0 && $model->is_bidding != 1){
                $requestParams = [
                    'appId' => $mediaAppId,
                    'positionId' => $model->ad_id,
                    'cpmFloor' => $model->price,
                ];
                try {
                    $timestampNonce++;
                    $res = HttpClient::connect('Ks', 'Position')->setTImestamp($timestampNonce)->setConf($mediaConf)->modCpmFloor($requestParams);
                }catch (\Exception $exception) {
                    Log::system('error', 'ks设置价格失败:调用接口错误', [
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'trace' => $exception->getTraceAsString(),
                        'execKey' => $execKey
                    ]);
                    $model->remark = '设置价格失败:调用接口错误';
                    $model->save();
                    continue;
                }
                if (data_get($res, 'result') != 1) {
                    $model->remark = '设置价格失败' . data_get($res, 'error_msg');
                    $model->save();
                    continue;
                }
            }
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
            'adStyle' => self::AD_SLOT_TYPE_NATIVE,
            'renderType' => 2,//渲染⽅式 2（模版渲染），1（⾃渲染）
            'materialTypeList' => [2, 6],//素材类型 1-竖版视频,2-横版视频,5-竖版图片,6-横版图片
            'multiTemplateParams' => [['templateId' => 1], ['templateId' => 2], ['templateId' => 3], ['templateId' => 4], ['templateId' => 5]]
        ];
    }

    /**
     * 创建插屏
     */
    protected function inter(): array
    {
        return [
            'adStyle' => self::AD_SLOT_TYPE_INTER,
            'renderType' => 2,//渲染⽅式 2（模版渲染），1（⾃渲染）
            'materialTypeList' => [1, 2, 5, 6],//素材类型 1-竖版视频,2-横版视频,5-竖版图片,6-横版图片
            'templateId' => 9,//渲染模版ID
        ];
    }

    /**
     * 创建全屏插屏
     */
    protected function interFull(): array
    {
        return $this->fullVideo();
    }

    protected function fullVideo(): array
    {
        return [
            'adStyle' => self::AD_SLOT_TYPE_FULL_VIDEO,
            'renderType' => 1,//渲染⽅式 2（模版渲染），1（⾃渲染）
            'materialTypeList' => [1],//素材类型 1-竖屏,2-横屏
        ];
    }

    /**
     * 创建开屏
     */
    protected function splash(): array
    {
        return [
            'adStyle' => self::AD_SLOT_TYPE_SPLASH,
            'renderType' => 2,//渲染⽅式 2（模版渲染），1（⾃渲染）
            'materialTypeList' => [1, 3],//素材类型 1-竖版视频, 3-单图
            'templateId' => 1000,//渲染模版ID
            'skipAdMode' => 0,//跳过按钮
            'countdownShow' => 1,//跳过按钮是否显示 倒计时
            'voice' => 1,//播放声音 1-静音
        ];
    }

    /**
     * 创建激励视频
     */
    protected function rewordVideo(): array
    {
        return [
            'adStyle' => self::AD_SLOT_TYPE_REWORD_VIDEO,
            'renderType' => 1,//渲染⽅式 2（模版渲染），1（⾃渲染）
            'materialTypeList' => [1],//视频播放方向 1-竖版视频,2-横版视频
            'rewardedType' => 11,//奖励名称 1-虚拟金币, 2-积分,3-生命, 4-体力值,5-通关机会, 6-新关卡机会, "7-阅读币,8-新章节（小说类）, 9-观影币, 10-观看机会, 11-其他
            'rewardedNum' => 0,//奖励数量
            'callbackStatus' => 0,//奖励发放设置 0-关闭（无需服务端创建）1-开启（需要服务端创建）
        ];
    }
}