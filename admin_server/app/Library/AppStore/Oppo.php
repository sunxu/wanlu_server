<?php

namespace App\Library\AppStore;

use App\Constants\StoreOppoConst;
use App\Constants\RedisConst;
use App\Utils\Log;
use App\Utils\RedisManager;

class Oppo extends StoreAbstract
{
    protected $domain = "https://oop-openapi-cn.heytapmobi.com";

    protected $company_conf = 'store_oppo';

    protected $store_conf = 'store_config_oppo';

    /**
     * @inheritDoc
     */
    public function detectChange(): bool
    {
        $storeAppInfo = $this->getStoreAppInfo();
        $appStorage = $this->getAppStorage();
        if(!$appStorage->store_info_oppo){
            $model = clone $appStorage;
            $model->store_info_oppo = $storeAppInfo;
            $model->saveOrFail();
            return false;
        }
        if(
            data_get($storeAppInfo, 'data.update_time') != data_get($appStorage->store_info_oppo, 'data.update_time') ||
            data_get($storeAppInfo, 'data.is_freeze') != data_get($appStorage->store_info_oppo, 'data.is_freeze') ||
            data_get($storeAppInfo, 'data.state') != data_get($appStorage->store_info_oppo, 'data.state') ||
            data_get($storeAppInfo, 'data.audit_status') != data_get($appStorage->store_info_oppo, 'data.audit_status')
        ){
            $model = clone $appStorage;
            $model->store_info_oppo = $storeAppInfo;
            $model->saveOrFail();
            return true;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    protected function buildMessage(): string
    {
        $appStorage = $this->getAppStorage();
        $appInfo = $this->getAppInfo();
        $version = data_get($appStorage->store_info_oppo, 'data.version_name');
        $updateTime = data_get($appStorage->store_info_oppo, 'data.update_time');
        $business_refuse_reason = data_get($appStorage->store_info_oppo, 'data.business_refuse_reason');
        $refuse_reason = data_get($appStorage->store_info_oppo, 'data.refuse_reason');
        $freeze_reason = data_get($appStorage->store_info_oppo, 'data.freeze_reason');
        $statusMessage = data_get($appStorage->store_info_oppo, 'data.audit_status_name');
        $state = data_get($appStorage->store_info_oppo, 'data.state');
        $stateMessage = StoreOppoConst::ON_LINE[$state];
        $is_freeze = data_get($appStorage->store_info_oppo, 'data.is_freeze');
        $is_freeze_message = StoreOppoConst::FREEZE[$is_freeze];

        return "**{$appInfo->app_name}** *{$version}* \n\n" .
            "**OPPO 商店** \n\n" .
            "> **应用状态** : {$statusMessage} \n\n" .
            "> **审核意见** : {$refuse_reason} \n\n" .
            "> **上架状态** : {$stateMessage} \n\n" .
            "> **更新时间** : {$updateTime} \n\n";
    }

    /**
     * @inheritDoc
     */
    public function getStoreAppInfo(): array
    {
        $storeConf = $this->getStoreConf();
        $packageName = data_get($storeConf, 'packageName');
        $appInfo = $this->request("get", "/resource/v1/app/info", ['pkg_name' => $packageName]);
        if(data_get($appInfo, 'errno') !== 0){
            throw new StoreException("{$this->appId} oppo app 信息失败");
        }
        return $appInfo;
    }

    protected function request(string $method, string $uri, array $params = []): array
    {
        $params = array_merge([
            'access_token' => $this->getAccessToken(),
            'timestamp' => time(),
        ], $params);
        $params['api_sign'] = $this->getSign($params);
        if(strtoupper($method) == 'GET'){
            $options = ['query'=>$params];
        }else{
            $options = ['form_params'=>$params];
        }
        return parent::request($method, $uri, $options);
    }

    protected function getSign(array $params): string
    {
        $companyConf = $this->getCompanyStoreConf();
        $secret = data_get($companyConf, 'conf.client_secret');
        ksort($params);
        // 转字符串
        $str = urldecode(http_build_query($params));
        return bin2hex(hash_hmac('sha256', $str, $secret, true));
    }

    protected function getAccessToken(): string
    {
        $company = $this->getCompanyInfo();
        $companyConf = $this->getCompanyStoreConf();
        $redis = RedisManager::get();
        $redisKey = RedisConst::OPPO_TOKEN . $company->company_id;
        if($redis->exists($redisKey)){
            return $redis->get($redisKey);
        }
        $requestData = [
            'client_id' => data_get($companyConf, 'conf.client_id'),
            'client_secret' => data_get($companyConf, 'conf.client_secret'),
        ];
        $responseContent = parent::request('GET', '/developer/v1/token', ['query' => $requestData]);
        if(data_get($responseContent, 'errno') != 0){
            Log::system('error', "【AppStore】{$this->appId} 获取oppo token失败", ['appId' => $this->appId, 'response' => $responseContent]);
            throw new StoreException("{$this->appId} 获取oppo token失败");
        }
        $token = data_get($responseContent, 'data.access_token');
        $expire_in = data_get($responseContent, 'data.expire_in');
        $redis->set($redisKey, $token);
        $redis->expire($redisKey, $expire_in - time());

        return $token;
    }
}