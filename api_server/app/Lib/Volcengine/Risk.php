<?php

namespace App\Lib\Volcengine;

use App\Services\Common\App;
use Exception;
use Volc\Service\BusinessSecurity;

class Risk
{
    /**
     * @return mixed
     * @throws Exception
     */
    protected function getClient()
    {
        $client = BusinessSecurity::getInstance();

        $conf = config('volcengine');

        if (empty($conf['access_key']) || empty($conf['secret_key'])) {
            throw new Exception('Invalid AccessKey');
        }

        $client->setAccessKey($conf['access_key']);
        $client->setSecretKey($conf['secret_key']);

        return $client;
    }

    /**
     * @param int $appId
     * @param string $service
     * @param string $parameters
     * @return mixed
     * @throws Exception
     */
    protected function riskDetect(int $appId, string $service, string $parameters)
    {
        $result = $this->getClient()->RiskDetect($appId, $service, $parameters);

        $result = json_decode($result, true);

        if (!App::isProduct()) {
            \Log::debug("【volcengine-risk】", [
                'app_id' => $appId,
                'service' => $service,
                'parameters' => $parameters,
                'result' => $result
            ]);
        }

        if (($result['Result']['Code'] ?? -1) !== 0) {
            throw new Exception($result['Result']['Message'] ?? '未知错误');
        }

        return [
            'score' => $result['Result']['Data']['Score'] ?? 9999,
            'targs' => $result['Result']['Data']['Tags'] ?? [],
            'detail' => $result['Result']['Data']['Detail'] ?? [],
        ];
    }

    /**
     * @param int $appId
     * @param string $bdDeviceId
     * @param string $accountId
     * @param int $operateTime
     * @param string $deviceType
     * @param string $deviceBand
     * @param string $userAgent
     * @param string $osVersion
     * @param string $clientIp
     * @param string $channel
     * @return mixed
     * @throws Exception
     */
    public function register(
        int $appId,
        string $bdDeviceId,
        string $accountId,
        int $operateTime,
        string $deviceType,
        string $deviceBand,
        string $userAgent,
        string $osVersion,
        string $clientIp,
        string $channel)
    {
        $json = json_encode([
            'account_id' => $accountId,
            'operate_time' => $operateTime,
            'device_platform' => 'android',
            'device_type' => $deviceType,
            'device_brand' => $deviceBand,
            'user_agent' => $userAgent,
            'os_version' => $osVersion,
            'ip' => $clientIp,
            'bd_did' => $bdDeviceId,
            'account_type' => 'other',
            'channel' => $channel
        ]);
        return $this->riskDetect($appId, 'register', $json);
    }

    /**
     * @param int $appId
     * @param string $bdDeviceId
     * @param string $accountId
     * @param int $operateTime
     * @param string $deviceType
     * @param string $deviceBand
     * @param string $userAgent
     * @param string $osVersion
     * @param string $clientIp
     * @param string $channel
     * @return mixed
     * @throws Exception
     */
    public function login(
        int $appId,
        string $bdDeviceId,
        string $accountId,
        int $operateTime,
        string $deviceType,
        string $deviceBand,
        string $userAgent,
        string $osVersion,
        string $clientIp,
        string $channel)
    {
        $json = json_encode([
            'account_id' => $accountId,
            'operate_time' => $operateTime,
            'device_platform' => 'android',
            'device_type' => $deviceType,
            'device_brand' => $deviceBand,
            'user_agent' => $userAgent,
            'os_version' => $osVersion,
            'ip' => $clientIp,
            'bd_did' => $bdDeviceId,
            'channel' => $channel,
            'login_type' => 'other',
            'oauth_id' => $accountId
        ]);
        return $this->riskDetect($appId, 'login', $json);
    }
}