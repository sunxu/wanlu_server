<?php

declare(strict_types=1);
/**
 *
 * 增长参谋
 * @see https://www.csjplatform.com/supportcenter/5792
 * Author: wangshenyuan
 * Date: 2022/2/21
 */

namespace App\Services\Common;

use App\Exceptions\Handler;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ZengZhangCanMou
{
    //归因数据上报配置
    const ATTRIBUTION_CONFIG = [
        'master' => [//wifi大师
            'package' => 'com.nls.android.wifimaster',
            'callbackUrl' => 'https://www.csjplatform.com/growth_dispatcher/api/attribution_callback_self_built/c3ef5dae-677f-1690-abbc-a01a156b4d38/android?pg_site_id=5241415&signature=',
            'secretKey' => '8e50f7905a905d0b'
        ]
    ];

    //投放数据上报配置
    const PUT_CONFIG = [
        'master' => [//wifi大师
            'secretKey' => '34da20a743794b0f',
            'userID' => '76219',
            'site_id' => 5241415,
            'package_name' => 'com.nls.android.wifimaster',
        ]
    ];

    //小时级数据地址
    const HOURLY_CALLBACK_URL = 'https://www.csjplatform.com/growth_mkt/open/api/v1/callback_hourly/';
    //天级数据上报地址
    const DAILY_CALLBACK_URL = 'https://www.csjplatform.com/growth_mkt/open/api/v1/callback_daily/';

    const TIMEOUT = 30;//接口请求超时时间（秒）

    private $appcode;

    private static $instance = null;

    /**
     * @return ZengZhangCanMou|null
     */
    public static function getInstance(): ?ZengZhangCanMou
    {
        if (self::$instance == null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * 设置appcode
     * @param string $appcode
     * @return ZengZhangCanMou
     */
    public function setAppCode(string $appcode): ZengZhangCanMou
    {
        $this->appcode = $appcode;
        return $this;
    }

    /**
     * 获取归因Signature
     * @param array $attrData
     * @param string $secretKey
     * @return string
     */
    private function generateAttributionSignature(array $attrData, string $secretKey): string
    {
        ksort($attrData, SORT_STRING);
        $preSignString = "";
        foreach ($attrData as $key => $value) {
            $preSignString .= $key . "=" . $value . "&";
        }
        $preSignString .= "secretKey=" . $secretKey;
        return md5($preSignString);
    }

    /**
     * 获取投放数据Signature
     * @param string $payload
     * @param string $userID
     * @param string $secretKey
     * @return string
     */
    private function generatePutSignature(string $payload, string $userID, string $secretKey): string
    {
        $preSignString = 'user_id=' . $userID . '&payload=' . $payload . '&secret_key=' . $secretKey;
        return md5($preSignString);
    }


    /**
     * 发送请求
     * @param $url
     * @param $data
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function sendData($url, $data)
    {
        $sendOpt = [
            'timeout' => self::TIMEOUT,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $data
        ];
        $curl = new Client();
        $response = $curl->post($url, $sendOpt)->getBody()->getContents();
        return json_decode($response, true);
    }


    /**
     * 发送归因请求
     * @see https://www.csjplatform.com/supportcenter/5803
     * @param array $attrData
     */
    public function sendAttributionData(array $attrData)
    {

        $config = self::ATTRIBUTION_CONFIG[$this->appcode] ?? null;//当前app配置信息
        if (!$config) {
            return;
        }

        $secretKey = $config['secretKey'];
        $callbackURL = $config['callbackUrl'];
        $attrData['package'] = $config['package'];

        /*接口上报所需参数*/
        $attrDataKeys = [
            'package',//app安装包
            'time_ts',//激活时间
            'ad_platform',//广告投放平台
            'imei',//安卓设备的设备标识
            'android_id',//安卓设备的设备标识
            'oaid',//安卓设备的设备标识
            'ip',//设备ip
            'ua',//设备User-Agent
            'ad_account_id',//广告账户id
            'campaign_id',//广告第一层级，广告组id
            'campaign_name',//广告第一层级，广告组名称
            'ad_id',//广告第二层级，广告计划id
            'ad_name',//广告第二层级，广告计划名称
            'creative_id',//广告第三层级，创意id
            'touch_type',//归因到的触点类型
        ];

        $attrData = collect($attrData)->only($attrDataKeys)->filter(function ($value) {
            if (!empty($value)) return true;
        })->toArray();//过滤多余参数



        if (env('APP_ENV') !== 'production') {
            Log::info('增长参谋归因请求参数：', $attrData);// 日志
        }


        $signature = $this->generateAttributionSignature($attrData, $secretKey);

        $sendCallbackURL = $callbackURL . $signature;

        try {
            $response = $this->sendData($sendCallbackURL, $attrData);
            var_dump($response);
            if (!isset($response['base_resp']) || !isset($response['base_resp']['status'])
                || $response['base_resp']['status'] != 0) {
                $res = var_export($response, true);
                throw new \Exception('增长参谋归因接口请求失败: ' . $res . '请求地址：' . $sendCallbackURL . '请求参数：' . json_encode($attrData));
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 发送天级/小时级请求
     * @see https://www.csjplatform.com/supportcenter/5810
     * @param string $temporal_granularity 时间粒度 天粒度（DAILY）／小时粒度（HOURLY）
     * @param array $payloads
     */
    public function sendDailyHourlyData(string $temporal_granularity, array $payloads)
    {
        $temporal_granularity_list = [
            'DAILY' => self::DAILY_CALLBACK_URL,
            'HOURLY' => self::HOURLY_CALLBACK_URL,
        ];

        $api_url = $temporal_granularity_list[$temporal_granularity] ?? null;
        if (!$api_url) {
            return;
        }

        $config = self::PUT_CONFIG[$this->appcode] ?? null;//当前app配置信息
        if (!$config) {
            return;
        }

        $page_size = 100;//最大上报数量
        $total_count = count($payloads);//总数量
        $total_page = ceil($total_count / $page_size);//总页数

        echo '增长参谋 总数量：' . $total_count . PHP_EOL;
        echo '增长参谋 总页数：' . $total_page . PHP_EOL;
        /*分页上报数据*/
        for ($page = 0; $page < $total_page; $page++) {
            $limit_start = $page * $page_size;
            $i = 0;
            $payload_page = [];
            while ($i < $page_size && isset($payloads[$limit_start + $i])) {
                $payload_fixed = [
                    'site_id' => $config['site_id'],
                    'package_name' => $config['package_name'],
                    'os' => 'android'
                ];
                $payload_page[] = array_merge($payloads[$limit_start + $i], $payload_fixed);
                $i++;
            }


            $userID = $config['userID'];
            $secretKey = $config['secretKey'];
            $payloadStr = json_encode($payload_page, JSON_UNESCAPED_UNICODE);
            $signature = $this->generatePutSignature($payloadStr, $userID, $secretKey);

            $data = [
                'user_id' => $userID,
                'signature' => $signature,
                'payload' => $payloadStr
            ];

            try {
//                Log::info('增长参谋请求参数：【' . $api_url . '】', $data);
                $response = $this->sendData($api_url, $data);
//                Log::info('增长参谋请求结果：', $response);
                if (!isset($response['base_resp']) || !isset($response['base_resp']['status'])
                    || $response['base_resp']['status'] != 0) {
                    $res = var_export($response, true);
                    throw new \Exception('增长参谋' . $temporal_granularity . '级数据接口请求失败: ' . $res);
                }
            } catch (\Exception $e) {
//                Log::info('增长参谋请求结果：【失败】' . $e->getMessage());
                (new Handler())->pushMessage($e);
            }

        }

    }
}
