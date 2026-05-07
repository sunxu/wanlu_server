<?php

declare(strict_types=1);

namespace App\Service\AdSlot;

use App\Library\HttpClient\HttpClient;
use App\Model\AppCompany;
use App\Utils\Log;

class AdSlotCsjService extends AdSlotMediaAbstract
{
    const PLATFORM = 'csj';

    const AD_SLOT_TYPE_NATIVE = 1;//代码位类型 - 信息流
    const AD_SLOT_TYPE_INTER = 9;//代码位类型 - 插屏
    const AD_SLOT_TYPE_INTER_FULL = 9;//代码位类型 - 全屏插屏
    const AD_SLOT_TYPE_SPLASH = 3;//代码位类型 - 开屏
    const AD_SLOT_TYPE_REWORD_VIDEO = 5;//代码位类型 - 激励视频

    protected function requestAppList($mediaConf)
    {
        $res = HttpClient::connect('Csjmedia', 'App')->setConf($mediaConf)->list();
        $appList = [];
        foreach (data_get($res, 'data.app_list') as $row) {
            $appList[] = [
                'app_id' => data_get($row, 'app_id'),
                'app_name' => data_get($row, 'app_name'),
                'package_name' => data_get($row, 'package_name'),
                'status' => in_array($row['status'], [2]) ? 1 : 0//2和6是正常状态 其他是停用状态
            ];
        }
        return $appList;
    }

    public function refreshApp($account)
    {
        $accountId = data_get($account, 'company_id');
        $mediaConf = data_get($account, 'media_conf_csj');
        if(!$mediaConf){
            return;
        }
        $appList = $this->requestAppList($mediaConf);
        $this->saveMediaApp($appList, 'csj', $accountId);
    }

    public function createAdSlot($accountId, $mediaAppId, $data, $execKey, $id = 0)
    {
        $conf = AppCompany::select('media_conf_csj')->where('company_id', $accountId)->first();
        $mediaConf = data_get($conf, 'media_conf_csj');
        foreach ($data as $item) {
            $model = $this->getAppMediaAdModel($accountId, $mediaAppId, self::PLATFORM, $item, $execKey, $id);
            $requestParams = [
                'ad_slot_name' => $model->ad_name,
                'app_id' => intval($mediaAppId),
                'use_mediation' => '1'
            ];
            if($model->is_bidding == 1){
                $requestParams['bidding_type'] = 1;
            }else if ($model->price && $model->price > 0) {
                $requestParams['cpm'] = strval($model->price);
            }
            $adTypeParams = $this->getAdTypeParams($model->ad_type_key);
            $requestParams = array_merge($requestParams, $adTypeParams);
            try {
                $res = HttpClient::connect('Csjmedia', 'Code')->setConf($mediaConf)->create($requestParams);
            }catch (\Exception $exception) {
                Log::system('error', 'csj创建失败:调用接口错误', [
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
            $model->ad_id = data_get($res, 'data.ad_slot_id');
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
            'ad_slot_type' => self::AD_SLOT_TYPE_NATIVE,
            'render_type' => 1,//渲染⽅式 1（模版渲染），2（⾃渲染）
            'accept_material_type' => 3,//代码位可接受素材类型 1-仅图⽚，2-仅视频，3-图⽚+视频，建议选3；
            'template_layouts' => [1, 2],//优选模版 1:上⽂下图 2:上图下⽂ 3:⽂字浮层 4:竖版 5:左图右⽂ 6:左⽂右图 7:三图
        ];
    }

    /**
     * 创建插屏
     */
    protected function inter(): array
    {
        return [
            'ad_slot_type' => self::AD_SLOT_TYPE_INTER,
            'render_type' => 1,//渲染类型 1（模板渲染）、3（⾮原⽣）
            'ad_rollout_size' => 2,//⼴告铺开⼤⼩ 1是全屏，2是半屏
            'accept_material_type' => 3,//⼴告素材类型 1是仅图⽚；2是仅视频；3是图⽚+视频
            'orientation' => 1,//视频播放⽅向/屏幕⽅向 1是竖版；2是横版
            'skip_duration' => 0, //n秒后显⽰跳过按钮 全屏数值范围为5-15s；插屏数值范围为0-15s
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
            'ad_slot_type' => self::AD_SLOT_TYPE_INTER_FULL,
            'render_type' => 1,//渲染类型 1（模板渲染）、3（⾮原⽣）
            'ad_rollout_size' => 1,//⼴告铺开⼤⼩ 1是全屏，2是半屏
            'accept_material_type' => 3,//⼴告素材类型 1是仅图⽚；2是仅视频；3是图⽚+视频
            'orientation' => 1,//视频播放⽅向/屏幕⽅向 1是竖版；2是横版
            'skip_duration' => 5, //n秒后显⽰跳过按钮 全屏数值范围为5-15s；插屏数值范围为0-15s
        ];
    }

    /**
     * 创建开屏
     */
    protected function splash(): array
    {
        return [
            'ad_slot_type' => self::AD_SLOT_TYPE_SPLASH,
            'render_type' => 3,//渲染⽅式 1（模板渲染）、3（⾮原⽣）
            'use_icon' => 2,//是否使⽤点睛样式 ⾮原⽣时⽣效  1（使⽤）2（不使⽤），不传值时默认使⽤
        ];
    }

    /**
     * 创建激励视频
     */
    protected function rewordVideo(): array
    {
        return [
            'ad_slot_type' => self::AD_SLOT_TYPE_REWORD_VIDEO,
            'render_type' => 1,//渲染⽅式 1（模板渲染）、3（⾮原⽣）
            'orientation' => 1,//视频播放⽅向 1（垂直）、2（⽔平）
            'reward_is_callback' => 0,//奖励发放设置 0（⽆需服务器判断）、1（需要服务器判断）
        ];
    }
}