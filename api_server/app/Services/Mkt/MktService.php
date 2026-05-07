<?php

namespace App\Services\Mkt;

use App\Constants\AdConst;
use App\Services\Common\Account;
use App\Services\Common\App;
use App\Services\Helper\Encrypt\Aes;
use App\Services\Helper\Helper;
use DB;
use Exception;
use Log;

class MktService
{
    /**
     * 平台
     * @var
     */
    protected $platform;

    public function __construct(int $source)
    {
        if (!in_array($source, AdConst::PUT_SOURCE_MAP)) {
            throw new Exception('invalid source');
        }
        $this->platform = array_flip(AdConst::PUT_SOURCE_MAP)[$source];
    }

    /**
     * @param string $eventType
     * @param int $eventTime
     * @param array $params
     * @return false
     * @throws Exception
     */
    public function callback(string $eventType, int $eventTime, array $params)
    {
        return call_user_func_array([$this, $this->platform], [$eventType, $eventTime, $params]);
    }

    /**
     * @param string $eventType
     * @return mixed
     * @throws Exception
     */
    protected function getEventType(string $eventType)
    {
        if (!isset(AdConst::PUT_CALLBACK_EVENT[$eventType])) {
            throw new Exception('invalid event type');
        }

        return AdConst::PUT_CALLBACK_EVENT[$eventType][$this->platform];
    }

    /**
     * @param string $eventType
     * @param int $eventTime
     * @param array $params
     * @return bool
     * @throws Exception
     */
    protected function jl(string $eventType, int $eventTime, array $params)
    {
        if (!filter_var($params['callback_url'] ?? '', FILTER_VALIDATE_URL)) {
            throw new Exception('invalid url:' . $params['callback_url']);
        }
        $url = sprintf('%s&event_type=%s&conv_time=%s', $params['callback_url'], $this->getEventType($eventType), $eventTime);

        $result = Helper::request($url);
        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'args' => func_get_args(),
            'data' => $url,
            'result' => $result
        ]);

        if (!empty($result['result'])) {
            $data = json_decode($result['result'], true);
            if (($data['code'] ?? -1) == 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $eventType
     * @param int $eventTime
     * @param array $params
     * @return bool
     * @throws Exception
     */
    protected function ks(string $eventType, int $eventTime, array $params)
    {
        if (!filter_var($params['callback_url'] ?? '', FILTER_VALIDATE_URL)) {
            throw new Exception('invalid url:' . $params['callback_url']);
        }
        $url = sprintf('%s&event_type=%s&event_time=%s', $params['callback_url'], $this->getEventType($eventType), $eventTime * 1000);

        $result = Helper::request($url);

        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'args' => func_get_args(),
            'data' => $url,
            'result' => $result
        ]);

        if (!empty($result['result'])) {
            $data = json_decode($result['result'], true);
            if (($data['result'] ?? -1) === 1) {
                return true;
            }
        }

        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'params' => [
                'app_code' => $params['app_code'],
                'eventType' => $eventType
            ],
            'result' => $result
        ]);

        return false;
    }

    /**
     * @param string $eventType
     * @param int $eventTime
     * @param array $params
     * @return bool
     * @throws Exception
     */
    protected function op(string $eventType, int $eventTime, array $params)
    {
        $signKey = 'e0u6fnlag06lc3pl';
        $aesKey = 'XGAXicVG5GMBsx5bueOe4w==';

        $app = App::getAppByCode($params['app_code']);
        // 判断快应用
        if ($app['sub_type'] == App::$appSubType['tool_quick']) {
            $extra = Account::extra($params['account_id'] ?? '');
            $pkg = $extra['pkg'] ?? '';

            // 快应用和APP关键行为不同
            $eventType = $eventType == 'key_action' ? 'quick_key_action' : $eventType;
            $appType = 3;
        } else {
            $pkg = $app['package_name'] ?? '';
            $appType = 1;
        }

        if (empty($pkg)) {
            throw new Exception('invalid package_name');
        }

        $data = json_encode([
            'imei' => !empty($params['imei']) ? Aes::encrypt($params['imei'], base64_decode($aesKey)) : '',
            'ouId' => !empty($params['oaid']) ? Aes::encrypt($params['oaid'], base64_decode($aesKey)) : '',
            'timestamp' => $eventTime * 1000,
            'pkg' => $pkg,// 包名或快应用id
            'dataType' => $this->getEventType($eventType),
            'channel' => 1,// 渠道:1、OPPO，2、一加，0、其他
            'type' => !empty($params['oaid']) ? 0 : 1,// 0: 无加密(默认为 0) 1:imei md5 加密 2:oaid md5 加密
            'appType' => $appType,// 1应用 2游戏 3快应用
            'ascribeType' => 1,// 1:广告主归因，0:OPPO 归因(默 认或者不填即为 0)，2: 助攻归因
            'adId' => $params['cid']
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $url = 'https://api.ads.heytapmobi.com/api/uploadActiveData';
        $timestamp = time() * 1000;

        $header = [
            'signature' => md5($data . $timestamp . $signKey),
            'timestamp' => $timestamp,
            'Content-Type' => 'application/json',
        ];

        $result = Helper::request($url, 'POST', $data, $header);
        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'args' => func_get_args(),
            'data' => $data,
            'result' => $result
        ]);

        if (!empty($result['result'])) {
            $response = json_decode($result['result'], true);
            if (($response['ret'] ?? -1) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $eventType
     * @param int $eventTime
     * @param array $params
     * @return bool
     * @throws Exception
     */
    protected function hw(string $eventType, int $eventTime, array $params)
    {
        $extra = Account::extra($params['account_id'] ?? '');

        $signKey = $extra['sign_key'] ?? '';
        if (!$signKey) {
            throw new Exception('invalid sign_key');
        }

        $url = 'https://ppscrowd-drcn.op.hicloud.com/action-lib-track/hiad/v2/actionupload';
        $timestamp = time() * 1000;

        $buildAuthorizationHeader = function (string $data, int $timestamp, string $signKey) {
            $signature = hash_hmac('sha256', $data, $signKey);
            return 'Digest validTime="' . $timestamp . '", response="' . $signature . '"';
        };

        $data = json_encode([
            'callback' => $params['callback'],
            'content_id' => $params['cid'],
            'campaign_id' => $params['aid'],
            'conversion_type' => $this->getEventType($eventType),
            'conversion_time' => $eventTime,
            'timestamp' => $timestamp,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $header = [
            'Authorization' => $buildAuthorizationHeader($data, $timestamp, $signKey),
            'Content-Type' => 'application/json',
        ];

        $result = Helper::request($url, 'POST', $data, $header);
        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'args' => func_get_args(),
            'data' => $data,
            'result' => $result
        ]);

        if (!empty($result['result'])) {
            $response = json_decode($result['result'], true);
            if (($response['resultCode'] ?? -1) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $eventType
     * @param int $eventTime
     * @param array $params
     * @return bool
     * @throws Exception
     */
    protected function vi(string $eventType, int $eventTime, array $params)
    {
        $extra = Account::extra($params['account_id'] ?? '');

        $signKey = $extra['src_id'] ?? '';
        if (!$signKey) {
            throw new Exception('invalid src_id');
        }
        $token = DB::connection('uc')->table('mkt_advertiser as a')
            ->leftJoin('mkt_account as b', 'b.account_id', '=', 'a.account_id')
            ->leftJoin('mkt_user as c', 'c.user_id', '=', 'b.user_id')
            ->where('a.advertiser_id', $params['account_id'])
            ->value('c.access_token');
        if (!$token) {
            throw new Exception('invalid access_token');
        }
        $getParam = [
            'access_token' => $token,
            'timestamp' => time() * 1000,
            'nonce' => uniqid(),
            'advertiser_id' => $params['account_id']
        ];

        $url = 'https://marketing-api.vivo.com.cn/openapi/v1/advertiser/behavior/upload?' . urldecode(http_build_query($getParam));
        $app = App::getAppByCode($params['app_code']);
        if (empty($app['package_name'])) {
            throw new Exception('invalid package_name');
        }
        // 判断快应用
        if ($app['sub_type'] == App::$appSubType['tool_quick']) {
            $src = [
                'srcType' => 'Web',
                'pageUrl' => "hap://app/{$app['package_name']}"
            ];
        } else {
            $src = [
                'srcType' => 'APP',
                'pkgName' => $app['package_name'] ?? '',
            ];
        }

        $data = json_encode(array_merge($src, [
            'srcId' => $extra['src_id'],
            'dataList' => [
                'userIdType' => empty($params['oaid']) ? 'IMEI_MD5' : 'OAID',
                'userId' => empty($params['oaid']) ? $params['imei'] : $params['oaid'],
                'cvType' => $this->getEventType($eventType),
                'cvCustom' => $eventType,
                'cvTime' => $eventTime * 1000,
                'requestId' => $params['request_id'],
                'creativeId' => $params['cid'],
            ],
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $result = Helper::request($url, 'POST', $data);

        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'args' => func_get_args(),
            'data' => $data,
            'result' => $result
        ]);

        if (!empty($result['result'])) {
            $response = json_decode($result['result'], true);
            if (($response['code'] ?? -1) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $eventType
     * @param int $eventTime
     * @param array $params
     * @return bool
     * @throws Exception
     */
    protected function bd(string $eventType, int $eventTime, array $params)
    {
        $extra = Account::extra($params['account_id'] ?? '');

        $signKey = $extra['akey'] ?? base64_encode($params['account_id']);
        if (!$signKey) {
            throw new Exception('invalid akey');
        }

        if (!filter_var($params['callback_url'] ?? '', FILTER_VALIDATE_URL)) {
            throw new Exception('invalid url:' . $params['callback_url']);
        }

        $url = str_replace('{{ATYPE}}', $this->getEventType($eventType), $params['callback_url']);
        $url = str_replace('{{AVALUE}}', 0, $url);
        $url = sprintf('%s&cb_event_time=%s', $url, $eventTime * 1000);

        $url = sprintf('%s&sign=%s', $url, md5($url . $signKey));

        $result = Helper::request($url);
        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'args' => func_get_args(),
            'data' => $url,
            'result' => $result
        ]);

        if (!empty($result['result'])) {
            $data = json_decode($result['result'], true);
            if (($data['error_code'] ?? -1) == 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $eventType
     * @param int $eventTime
     * @param array $params
     * @return bool
     * @throws Exception
     */
    protected function honor(string $eventType, int $eventTime, array $params)
    {
        $url = 'https://ads-drcn.platform.hihonorcloud.com/api/ad-tracking/v1/conversion';
        $result = Helper::request($url, 'GET', [
            'trackId' => $params['track_id'] ?? '',
            'conversionId' => $this->getEventType($eventType),
            'conversionTime' => $eventTime * 1000,
            'advertiserId' => $params['account_id'] ?? '',
        ]);
        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'args' => func_get_args(),
            'data' => $url,
            'result' => $result
        ]);

        if (!empty($result['result'])) {
            $data = json_decode($result['result'], true);
            if (($data['code'] ?? -1) == 0) {
                return true;
            }
        }

        return false;
    }
}