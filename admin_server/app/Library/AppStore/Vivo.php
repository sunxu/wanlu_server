<?php

namespace App\Library\AppStore;

use App\Constants\StoreVivoConst;

class Vivo extends StoreAbstract
{
    protected $domain = "https://developer-api.vivo.com.cn/router/rest";

    protected $company_conf = 'store_vivo';

    protected $store_conf = 'store_config_vivo';

    /**
     * @inheritDoc
     */
    public function detectChange(): bool
    {
        $storeAppInfo = $this->getStoreAppInfo();
        $appStorage = $this->getAppStorage();
        if(!$appStorage->store_info_vivo){
            $model = clone $appStorage;
            $model->store_info_vivo = $storeAppInfo;
            $model->saveOrFail();
            return false;
        }
        if(
            data_get($storeAppInfo, 'data.versionName') != data_get($appStorage->store_info_vivo, 'data.versionName') ||
            data_get($storeAppInfo, 'data.status') != data_get($appStorage->store_info_vivo, 'data.status') ||
            data_get($storeAppInfo, 'data.saleStatus') != data_get($appStorage->store_info_vivo, 'data.saleStatus')
        ){
            $model = clone $appStorage;
            $model->store_info_vivo = $storeAppInfo;
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
        $version = data_get($appStorage->store_info_vivo, 'data.versionName');
        $status = data_get($appStorage->store_info_vivo, 'data.status');
        $saleStatus = data_get($appStorage->store_info_vivo, 'data.saleStatus');
        $statusMessage = StoreVivoConst::AUDIT[$status];
        $saleStatusMessage = StoreVivoConst::PUT_AWAY[$saleStatus];
        if ($saleStatusMessage == StoreVivoConst::PUT_AWAY[StoreVivoConst::VIVO_PUTAWAY_DOW]){
            return "**{$appInfo->app_name}** *{$version}* \n\n" .
                "**VIVO 商店** \n\n" .
                "> **上架状态** : {$saleStatusMessage} \n\n";
        }
        return "**{$appInfo->app_name}** *{$version}* \n\n" .
            "**VIVO 商店** \n\n" .
            "> **应用状态** : {$statusMessage} \n\n" .
            "> **上架状态** : {$saleStatusMessage} \n\n";
    }

    /**
     * @inheritDoc
     */
    public function getStoreAppInfo(): array
    {
        $storeConf = $this->getStoreConf();
        $requestData = [
            'packageName' => data_get($storeConf, 'packageName'),
            'method' => 'app.query.details'
        ];
        $appInfo = $this->request('post', '', $requestData);
        if(data_get($appInfo, 'code') !== 0){
            throw new StoreException("{$this->appId} 获取vivo app 信息失败");
        }
        return $appInfo;
    }

    protected function request(string $method, string $uri, array $params = []): array
    {
        $companyConf = $this->getCompanyStoreConf();
        $msectime = msectime();
        $params = array_merge([
            'access_key' => data_get($companyConf, 'conf.access_key'),
            'timestamp' => $msectime,
            'format' => 'json',
            'v' => '1.0',
            'sign_method' => 'hmac',
            'target_app_key' => 'developer',
        ], $params);
        $params['sign'] = $this->getSign($params);
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
        $secret = data_get($companyConf, 'conf.access_secret');
        ksort($params);
        $str = urldecode(http_build_query($params));
        return bin2hex(hash_hmac('sha256', $str, $secret, true));
    }
}