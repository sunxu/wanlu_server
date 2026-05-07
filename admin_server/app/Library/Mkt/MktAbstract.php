<?php

declare(strict_types=1);

namespace App\Library\Mkt;

use App\Utils\Http;
use Exception;
use GuzzleHttp\Client;

abstract class MktAbstract
{
    protected $baseUri;

    protected $appId;

    protected $appSecret;

    protected $token;

    protected $appInfo;

    abstract public function request(string $uri = '', string $method = 'GET', array $params = [], array $headers = []);

    abstract public function authAccessToken(string $authCode);

    abstract public function authRefreshToken(string $refreshToken);

    abstract public function authUserInfo();

    abstract public function authAccountGet();

    abstract public function advertiserGet(string $accountId);

    abstract public function planGet(string $advertiserId);

    abstract public function planUpdate(string $advertiserId, string $planId, array $params = []);

    abstract public function planUpdateStatus(string $advertiserId, string $planId, string $putStatus);

    abstract public function planUpdateBudget(string $advertiserId, string $planId, float $budgetAmount);

    abstract public function reportAdvertiserGet(string $advertiserId, string $date);

    abstract public function reportPlanGet(string $advertiserId, string $date);

    abstract public function reportProjectGet(string $advertiserId, string $date);

    abstract public function reportAdGet(string $advertiserId, string $date);

    public function getFund(string $advertiserId) {
        return [];
    }

    /**
     * @param string $token
     * @return $this
     */
    public function setToken(string $token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getToken()
    {
        if (!$this->token) {
            throw new Exception('token is empty.');
        }

        return $this->token;
    }

    /**
     * 设置APP信息
     * @param array $appInfo
     * @return $this
     */
    public function setAppInfo(array $appInfo)
    {
        $this->appInfo = $appInfo;
        return $this;
    }

    public function getAppInfo()
    {
        return $this->appInfo;
    }

    /**
     * 设置应用信息
     * @param string $appId
     * @param string $appSecret
     * @return $this
     */
    public function setApp(string $appId, string $appSecret)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;

        return $this;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getApp()
    {
        if (!$this->appId || !$this->appSecret) {
            throw new Exception('appId or appSecret is empty.');
        }

        return [
            'appId' => $this->appId,
            'appSecret' => $this->appSecret
        ];
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getAppId()
    {
        if (!$this->appId) {
            throw new Exception('appId is empty.');
        }

        return $this->appId;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getAppSecret()
    {
        if (!$this->appSecret) {
            throw new Exception('appSecret is empty.');
        }

        return $this->appSecret;
    }

    /**
     * @param string $baseUri
     * @return Client
     */
    public function getClient(string $baseUri = null)
    {
        return make(Http::class)->get([
            'base_uri' => $baseUri ?? $this->baseUri,
//            'timeout' => 2.0,
        ]);
    }
}
