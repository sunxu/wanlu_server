<?php

namespace App\Services\Mkt;

use App\Constants\AdConst;
use App\Services\Helper\Helper;
use DB;
use Exception;
use Log;

class TencentService
{
    public function callback(string $event, int $time, array $params): bool
    {
        $appCode = (string)($params['app_code'] ?? '');
        if ($this->callbackEnabled($appCode)) {
            return $this->privateCallback($event, $time, $params);
        }
        return $this->legacyCallback($event, $time, $params);
    }

    protected function callbackEnabled(string $appCode): bool
    {
        $allowed = getenv('TX_DATANEXUS_CALLBACK_APPS');
        if ($allowed === false) {
            $allowed = $_ENV['TX_DATANEXUS_CALLBACK_APPS'] ?? ($_SERVER['TX_DATANEXUS_CALLBACK_APPS'] ?? '');
        }
        return $appCode !== '' && in_array($appCode, array_map('trim', explode(',', (string)$allowed)), true);
    }

    public static function privateCredentialId(string $appCode): string
    {
        if (!preg_match('/^[a-z0-9_]{1,50}$/D', $appCode)) {
            throw new Exception('腾讯业务 App 标识无效');
        }
        return 'txapp_' . sha1($appCode);
    }

    protected function privateCallback(string $event, int $time, array $params): bool
    {
        $appCode = (string)($params['app_code'] ?? '');
        $accountId = (string)($params['account_id'] ?? '');
        $credentialId = self::privateCredentialId($appCode);
        if (!ctype_digit($accountId) || $accountId === '0') {
            throw new Exception('腾讯广告主 ID 无效');
        }
        // 第一次查询仅验证账户及广告主绑定。不能沿 mkt_account.user_id 读取 Token。
        $account = DB::connection('uc')->table('mkt_advertiser as a')
            ->join('mkt_account as b', 'b.account_id', '=', 'a.account_id')
            ->where('a.advertiser_id', $accountId)
            ->where('a.app_code', $appCode)->where('a.status', 1)
            ->where('a.platform', AdConst::PUT_SOURCE_TX)
            ->where('b.is_valid', 1)
            ->first(['a.extra']);
        $account = is_object($account) ? (array)$account : (is_array($account) ? $account : []);
        if (!$account) {
            throw new Exception('腾讯广告主与业务 App 未绑定或账户无效');
        }
        $credential = DB::connection('uc')->table('mkt_user as c')
            ->join('mkt_app as d', 'd.app_id', '=', 'c.app_id')
            ->where('c.user_id', $credentialId)->where('d.platform', AdConst::PUT_SOURCE_TX)
            ->first(['c.access_token', 'c.access_time']);
        $credential = is_object($credential) ? (array)$credential : (is_array($credential) ? $credential : []);
        if (empty($credential['access_token']) || (int)($credential['access_time'] ?? 0) <= time() + 60) {
            throw new Exception('腾讯私有应用 Token 缺失或即将过期');
        }
        $callbackUrl = (string)($params['callback_url'] ?? '');
        TencentCallbackUrl::assertPostTarget($callbackUrl);
        $extra = $account['extra'] ?? [];
        if (is_string($extra)) {
            $extra = json_decode($extra, true);
        }
        $payload = $this->callbackPayload($event, $time, $params, is_array($extra) ? $extra : []);
        $response = $this->requestCallback($callbackUrl, $payload, $credential['access_token']);
        Log::info('腾讯 DataNexus Callback', [
            'app_code' => $appCode, 'advertiser_id' => $accountId,
            'code' => $response['code'] ?? -1, 'trace_id' => $response['trace_id'] ?? null,
        ]);
        return (int)($response['code'] ?? -1) === 0;
    }

    protected function requestCallback(string $url, array $payload, string $accessToken): array
    {
        TencentCallbackUrl::assertPostTarget($url);
        $handle = curl_init($url);
        if ($handle === false) {
            return [];
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'access-token: ' . $accessToken,
                'timestamp: ' . time(),
                'nonce: ' . bin2hex(random_bytes(16)),
                'cache-control: no-cache',
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $raw = curl_exec($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        if ($raw === false || (int)$httpCode !== 200) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function legacyCallback(string $event, int $time, array $params): bool
    {
        // 使用当前绑定和扩展配置，避免刚保存的行为源配置被旧账户缓存遮蔽。
        $auth = DB::connection('uc')->table('mkt_advertiser as a')
            ->join('mkt_account as b', 'b.account_id', '=', 'a.account_id')
            ->join('mkt_user as c', 'c.user_id', '=', 'b.user_id')
            ->join('mkt_app as d', 'd.app_id', '=', 'c.app_id')
            ->where('a.advertiser_id', $params['account_id'] ?? '')
            ->where('a.app_code', $params['app_code'] ?? '')->where('a.status', 1)
            ->where('a.platform', AdConst::PUT_SOURCE_TX)->where('d.platform', AdConst::PUT_SOURCE_TX)
            ->where('b.is_valid', 1)->first(['c.access_token', 'c.access_time', 'a.extra']);
        // Query Builder::first() normally returns stdClass, while isolated
        // adapters may return arrays. Normalize both without dereferencing null.
        $auth = is_object($auth) ? (array)$auth : (is_array($auth) ? $auth : []);
        if (empty($auth['access_token']) || (int)($auth['access_time'] ?? 0) <= time()) {
            throw new Exception('腾讯授权令牌无效或已过期');
        }
        $extra = json_decode($auth['extra'] ?? '{}', true);
        $payload = $this->payload($event, $time, $params, is_array($extra) ? $extra : []);
        $url = 'https://api.e.qq.com/v3.0/user_actions/add?' . http_build_query([
            'access_token' => $auth['access_token'], 'timestamp' => time(), 'nonce' => bin2hex(random_bytes(16)),
        ], '', '&', PHP_QUERY_RFC3986);
        $response = $this->request($url, $payload);
        // 不记录携带 access_token 的 URL、设备标识或完整回传数据。
        Log::info('腾讯行为回传', ['account_id' => $params['account_id'], 'event' => $event, 'code' => $response['code'] ?? -1]);
        return (int)($response['code'] ?? -1) === 0;
    }

    protected function request(string $url, array $payload): array
    {
        $result = Helper::request($url, 'POST', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ['Content-Type' => 'application/json']);
        if ((int)($result['http_code'] ?? 0) !== 200 || !empty($result['err_no'])) {
            return [];
        }
        return (array)json_decode($result['result'] ?? '', true);
    }

    public function callbackPayload(string $event, int $time, array $params, array $extra): array
    {
        return ['actions' => [$this->makeAction($event, $time, $params, $extra)]];
    }

    public function payload(string $event, int $time, array $params, array $extra): array
    {
        $accountId = (string)($params['account_id'] ?? '');
        $setId = (string)($extra['user_action_set_id'] ?? '');
        $clickId = (string)($params['click_id'] ?? '');
        if (!ctype_digit($accountId) || (int)$accountId <= 0 || !ctype_digit($setId) || (int)$setId <= 0 || $clickId === '' || strlen($clickId) > 64 || $time <= 0) {
            throw new Exception('腾讯回传缺少有效账户、行为源、点击标识或事件时间');
        }
        return ['account_id' => (int)$accountId, 'user_action_set_id' => (int)$setId,
            'actions' => [$this->makeAction($event, $time, $params, $extra)]];
    }

    protected function makeAction(string $event, int $time, array $params, array $extra): array
    {
        $accountId = (string)($params['account_id'] ?? '');
        $clickId = (string)($params['click_id'] ?? '');
        if ($clickId === '' || strlen($clickId) > 64 || $time <= 0 || $time > 2147483647) {
            throw new Exception('腾讯 Callback 缺少有效点击标识或事件时间');
        }
        $types = ['activate' => 'ACTIVATE_APP', 'keep1' => 'START_APP', 'key_action' => $extra['key_action_type'] ?? '', 'add_desktop' => $extra['key_action_type'] ?? 'ADD_DESKTOP'];
        $type = $types[$event] ?? '';
        if (!is_string($type) || !preg_match('/^[A-Z][A-Z_]{0,63}$/D', $type)) {
            throw new Exception('请在广告主扩展配置中填写腾讯 key_action_type，且与腾讯转化目标一致');
        }
        $user = [];
        foreach (['imei' => 'hash_imei', 'oaid' => 'hash_oaid', 'android_id' => 'hash_android_id'] as $key => $field) {
            $value = (string)($params[$key] ?? '');
            if ($value !== '') {
                // OAID 是点击下发的原值；IMEI / AndroidID 已在点击接收时规范成 MD5。
                $user[$field] = $key === 'oaid'
                    ? (preg_match('/^[a-f0-9]{32}$/iD', $value) ? strtolower($value) : md5($value))
                    : strtolower($value);
                if (!preg_match('/^[a-f0-9]{32}$/D', $user[$field])) {
                    throw new Exception('腾讯回传设备标识格式错误');
                }
            }
        }
        if (!$user) {
            throw new Exception('腾讯 APP 回传缺少设备标识');
        }
        $action = [
            'action_time' => $time, 'action_type' => $type, 'user_id' => $user,
            'trace' => ['click_id' => $clickId],
            // 以原始事件确定幂等标识，任务重试不改变该值。
            'outer_action_id' => 'tx_' . hash('sha256', ($params['app_code'] ?? '') . '|' . $accountId . '|' . $clickId . '|' . $event . '|' . $time),
        ];
        // DataNexus requires retention day for START_APP next-day retention.
        if ($type === 'START_APP') {
            $action['action_param'] = ['length_of_stay' => 1];
        }
        if ($type === 'CUSTOM') {
            $custom = $extra['custom_action'] ?? '';
            if (!is_string($custom) || trim($custom) === '' || strlen($custom) > 128) {
                throw new Exception('腾讯 CUSTOM 事件需要配置 custom_action');
            }
            $action['custom_action'] = $custom;
        }
        return $action;
    }
}
