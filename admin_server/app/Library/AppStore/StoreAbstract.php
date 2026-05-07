<?php

declare(strict_types=1);

namespace App\Library\AppStore;

use App\Constants\ErrorCode;
use App\Model\App;
use App\Model\AppCompany;
use App\Model\AppStore;
use App\Utils\Ding;
use App\Utils\Http;
use App\Utils\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

abstract class StoreAbstract
{
    protected $appId;

    protected $dingRobot = 'app_store';

    public function __construct(int $appId)
    {
        $this->appId = $appId;
    }

    /**
     * 获取 app 信息
     * @return App
     */
    public function getAppInfo(): App
    {
        return App::findOrFail($this->appId);
    }

    /**
     * 获取公司信息
     * @return array
     */
    public function getCompanyInfo(): AppCompany
    {
        $appInfo = $this->getAppInfo();
        if(!$appInfo->company_id){
            Log::system('error', "【AppStore】{$this->appId} 未配置公司主体", ['appId' => $this->appId]);
            throw new StoreException("{$this->appId} 未配置公司主体");
        }
        return AppCompany::findOrFail($appInfo->company_id);
    }

    public function getAppStorage(): AppStore
    {
        return AppStore::findOrFail($this->appId);
    }

    /**
     * 检测商店 app 信息是否改变
     * @return bool
     */
    abstract public function detectChange(): bool;

    /**
     * 构建消息
     * @return string
     */
    abstract protected function buildMessage(): string;

    public function sendMessage(): void
    {
        $message = $this->buildMessage();
        $this->getDing()->pushMarkdown('商店审核通知', $message);
    }

    protected function getDing(): Ding
    {
        return make(Ding::class, [$this->getDingRobot()]);
    }

    public function getDingRobot(): string
    {
        return $this->dingRobot;
    }

    public function setDingRobot($dingRobot): StoreAbstract
    {
        $this->dingRobot = $dingRobot;

        return $this;
    }

    /**
     * 获取app商店信息
     * @return array
     */
    abstract public function getStoreAppInfo(): array;

    /**
     * 获取 http 客户端
     * @return Client
     */
    protected function getClient(): Client
    {
        if(!$this->domain){
            Log::system('error', "【AppStore】{$this->appId} 未配置请求域名", ['appId' => $this->appId]);
            throw new StoreException("{$this->appId} 未配置请求域名");
        }
        return make(Http::class)->get(['base_uri' => $this->domain]);
    }

    protected function getStoreConf(): array
    {
        $column = $this->store_conf;
        $appStorage = $this->getAppStorage();
        $conf = $appStorage->$column;
        if(!$conf){
            Log::system('error', "【AppStore】{$column} 未配置", ['appId' => $this->appId]);
            throw new StoreException("AppStore】{$column} 未配置");
        }

        return $conf;
    }

    protected function getCompanyStoreConf(): array
    {
        $column = $this->company_conf;
        $company = $this->getCompanyInfo();
        $conf = $company->$column;
        if(!$conf){
            Log::system('error', "【AppStore】{$column} 未配置", ['appId' => $this->appId]);
            throw new StoreException("AppStore】{$column} 未配置");
        }

        return $conf;
    }

    protected function request(string $method, string $uri, array $options = []): array
    {
        Log::http('info', '【AppStore】 请求开始', ['appId' => $this->appId, 'uri' => $uri, 'options' => $options]);
        $startTime = microtime(true);
        try {
            $response = $this->getClient()->request(strtoupper($method), $uri, $options);
        }catch (GuzzleException $exception){
            Log::http('error', '【AppStore】请求错误', ['message' => $exception->getMessage(), 'appId' => $this->appId]);
            throw new StoreException("请求错误", ErrorCode::SERVER_ERROR, $exception);
        }
        $responseContent = json_decode($response->getBody()->getContents(), true);
        Log::http('info', '【AppStore】 请求结束', ['appId' => $this->appId, 'uri' => $uri, 'options' => $options, 'response' => $responseContent, 'resp_time' => microtime(true) - $startTime,]);
        return $responseContent;
    }
}
