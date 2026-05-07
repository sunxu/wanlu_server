<?php

declare(strict_types=1);

namespace App\Library\AppStore;

use App\Constants\StoreHuaweiConst;
use App\Constants\RedisConst;
use App\Utils\Log;
use App\Utils\RedisManager;

class Huawei extends StoreAbstract
{
    protected $domain = "https://connect-api.cloud.huawei.com";

    protected $company_conf = 'store_huawei';

    protected $store_conf = 'store_config_huawei';

    public function detectChange(): bool
    {
        $storeAppInfo = $this->getStoreAppInfo();
        $appStorage = $this->getAppStorage();
        if(!$appStorage->store_info_huawei){
            $model = clone $appStorage;
            $model->store_info_huawei = $storeAppInfo;
            $model->saveOrFail();
            return false;
        }
        if(
            data_get($storeAppInfo, 'appInfo.releaseState') != data_get($appStorage->store_info_huawei, 'appInfo.releaseState') ||
//            data_get($storeAppInfo, 'appInfo.updateTime') != data_get($appStorage->store_info_huawei, 'appInfo.updateTime') ||
            data_get($storeAppInfo, 'appInfo.auditOpinion') != data_get($appStorage->store_info_huawei, 'appInfo.auditOpinion') ||
            data_get($storeAppInfo, 'appInfo.versionNumber') != data_get($appStorage->store_info_huawei, 'appInfo.versionNumber')
        ){
            $model = clone $appStorage;
            $model->store_info_huawei = $storeAppInfo;
            $model->saveOrFail();
            return true;
        }
        return false;
    }

    public function getStoreAppInfo(): array
    {
        $appId = data_get($this->getStoreConf(), 'app_id');
        $appInfo = $this->request("GET", "/api/publish/v2/app-info?appId=$appId");
        if(data_get($appInfo, 'ret.code') !== 0){
            throw new StoreException("{$this->appId} 获取华为 app 信息失败");
        }
        return $appInfo;
    }

    protected function buildMessage(): string
    {
        $appStorage = $this->getAppStorage();
        $appInfo = $this->getAppInfo();
        $version = data_get($appStorage->store_info_huawei, 'appInfo.versionNumber');
        $status = data_get($appStorage->store_info_huawei, 'appInfo.releaseState');
        $statusMessage = StoreHuaweiConst::STATUS[$status];
        $updateTime = data_get($appStorage->store_info_huawei, 'appInfo.updateTime');
        $auditMessage = data_get($appStorage->store_info_huawei, 'auditInfo.auditOpinion');
        $message = "**{$appInfo->app_name}** *{$version}* \n\n" .
            "**华为商店** \n\n" .
            "> **应用状态** : {$statusMessage} \n\n";
        if($status != StoreHuaweiConst::HUAWEI_RELEASE_STATUS_AUDITING){
            $message .= "> **审核意见** : {$auditMessage} \n\n";
        }

        return $message . "> **更新时间** : {$updateTime} \n\n";
    }

    protected function request(string $method, string $uri, array $params = []): array
    {
        $options = $this->buildOptions($params);
        return parent::request($method, $uri, $options);
    }

    protected function buildOptions(array $params): array
    {
        $companyConf = $this->getCompanyStoreConf();
        $token = $this->getAccessToken();
        return [
            'json' => $params,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'client_id' => data_get($companyConf, 'conf.client_id'),
            ],
        ];
    }

    protected function getAccessToken(): string
    {
        $company = $this->getCompanyInfo();
        $companyConf = $this->getCompanyStoreConf();
        $redis = RedisManager::get();
        $redisKey = RedisConst::HUAWEI_TOKEN . $company->company_id;
        if($redis->exists($redisKey)){
            return $redis->get($redisKey);
        }
        $url = "/api/oauth2/v1/token";
        $requestData = [
            'grant_type' => 'client_credentials',
            'client_id' => data_get($companyConf, 'conf.client_id'),
            'client_secret' => data_get($companyConf, 'conf.client_secret'),
        ];
        $requestHeader = [
            'Content-Type' => 'application/json'
        ];
        $responseContent = parent::request('POST', $url, ['json' => $requestData, 'headers' => $requestHeader]);
        $token = data_get($responseContent, 'access_token');
        $expires = data_get($responseContent, 'expires_in');
        if(!$token){
            Log::system('error', "【AppStore】{$this->appId} 获取华为token失败", ['appId' => $this->appId, 'response' => $responseContent]);
            throw new StoreException("{$this->appId} 获取华为token失败");
        }
        $redis->set($redisKey, $token);
        $redis->expire($redisKey, $expires);

        return $token;
    }
}