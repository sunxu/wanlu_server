<?php

declare(strict_types=1);

namespace App\Service\AdSlot;

use App\Constants\AppConst;
use App\Model\AppMediaAd;
use App\Model\AppMediaApp;

abstract class AdSlotMediaAbstract
{
    abstract protected function requestAppList($mediaConf);

    abstract public function refreshApp($account);

    abstract public function createAdSlot($accountId, $mediaAppId, $data, $execKey, $id);

    abstract protected function native(): array;
    abstract protected function inter(): array;
    abstract protected function interFull(): array;
    abstract protected function splash(): array;
    abstract protected function rewordVideo(): array;
    abstract protected function fullVideo(): array;

    protected function saveMediaApp($appList, $platform, $accountId)
    {
        foreach ($appList as $row) {
            $model = AppMediaApp::where('platform', $platform)
                ->where('app_id', $row['app_id'])
                ->where('account_id', $accountId)
                ->first();
            $model = !$model? new AppMediaApp(): $model;
            $model->account_id = $accountId;
            $model->app_id = $row['app_id'];
            $model->platform = $platform;
            $model->app_name = $row['app_name'];
            $model->package_name = $row['package_name'];
            $model->status = $row['status'];
            $model->save();
        }
    }

    /**
     * 获取广告名称
     * @param $platform
     * @param $data
     * @return string
     */
    protected function getAdName($platform, $data): string
    {
        $adType = data_get($data, 'ad_type');
        $price = data_get($data, 'price') ?: 0;
        if (data_get($data, 'gmid')) {
            $platform = "{$platform}-gm";
        }
        if (data_get($data, 'is_bidding') == 'T') {
            $price = 'bidding';
        }
        // 加入随机唯一字符串 防止重复
        return "{$platform}-{$adType}-{$price}-" . uniqid();
    }

    /**创建广告位实例
     * @param $accountId
     * @param $mediaAppId
     * @param $platform
     * @param $data
     * @param $execKey
     * @return AppMediaAd
     */
    protected function getAppMediaAdModel($accountId, $mediaAppId, $platform, $data, $execKey, $id): AppMediaAd
    {
        if ($id > 0) {
            return AppMediaAd::find($id);
        }
        $platform = strtolower($platform);
        $model = new AppMediaAd();
        $model->account_id = $accountId;
        $model->media_app_id = $mediaAppId;
        $model->gmid = data_get($data, 'gmid')?:'';
        $model->ad_type = data_get($data, 'ad_type');
        $model->ad_type_key = data_get($data, 'ad_type_key');
        $model->price = data_get($data, 'price')?:0;
        $model->platform = $platform;
        $model->ad_name = $this->getAdName($platform, $data);
        $model->exec_key = $execKey;
        $model->is_bidding = data_get($data, 'is_bidding') == 'T'?1:0;
        $model->save();

        return $model;
    }

    /**
     * 获取广告类型相关的参数
     * @param $adType
     * @return array
     */
    protected function getAdTypeParams($adType): array
    {
        switch ($adType) {
            case AppConst::AD_TYPE_NATIVE:
                return $this->native();
            case AppConst::AD_TYPE_INTER:
                return $this->inter();
            case AppConst::AD_TYPE_INTER_FULL:
                return $this->interFull();
            case AppConst::AD_TYPE_SPLASH:
                return $this->splash();
            case AppConst::AD_TYPE_REWORD_VIDEO:
                return $this->rewordVideo();
            case AppConst::AD_TYPE_FULL_VIDEO:
                return $this->fullVideo();
        }
        return [];
    }
}