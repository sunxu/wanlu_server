<?php

namespace App\Services\Mkt;

use App\Constants\AdConst;
use App\Services\Helper\Helper;
use DB;
use Exception;
use Log;

class TencentService
{
    /**
     * 直接按点击记录中原样保存的 __CALLBACK__ 选择上报协议。
     * 只有没有 Callback URL 的历史记录才沿用独立行为源接口。
     * 非空但非法的 URL 一律拒绝，绝不能回退到 legacyCallback。
     */
    public function callback(string $event, int $time, array $params): bool
    {
        $url = $params['callback_url'] ?? null;
        if ($url === null || $url === '') {
            return $this->legacyCallback($event, $time, $params);
        }

        $appCode = (string)($params['app_code'] ?? '');
        try {
            if (!is_string($url)) {
                throw new \\InvalidArgumentException('腾讯 Callback URL 类型无效');
            }
            // 从真实点击读取目标 URL；先验证白名单，再进入两种 Callback 分支。
            TencentCallbackUrl::assertClickTarget($url);
            return $this->datanexusCallback($event, $time, $params);
        } catch (\\Throwable $e) {
            // 日志只保留固定分类；禁止记录 cb、Token、设备标识或任意异常消息。
            Log::info('腾讯 Callback 预检失败', [
                'app_code' => $appCode,
                'reason_code' => $this->callbackFailureCode($e),
            ]);
            throw $e;
        }
    }

    public static function privateCredentialId(string $appCode): string
    {
        if (!preg_match('/^[a-z0-9_]{1,50}$/D', $appCode)) {
            throw new Exception('腾讯业务 App 标识无效');
        }
        return 'txapp_' . sha1($appCode);
    }

    /**
     * 只要点击记录有合法 __CALLBACK__，即按目标协议使用 Callback。
     * HTTP tracking 关闭鉴权时不读取、不发送 Token；HTTPS api.e.qq.com 才取私有 Token。
     */
    protected function datanexusCallback(string $event, int $time, array $params): bool
    {
        $appCode = (string)($params['app_code'] ?? '');
        $accountId = (string)($params['account_id'] ?? '');
        if (!ctype_digit($accountId) || $accountId === '0') {
            throw new Exception('腾讯广告主 ID 无效');
        }

        $callbackUrl = (string)($params['callback_url'] ?? '');
        $unauthenticated = TencentCallbackUrl::isUnauthenticatedPostTarget($callbackUrl);
        if (!$unauthenticated) {
            TencentCallbackUrl::assertPostTarget($callbackUrl);
        }

        // 两种 Callback 均必须验证实际广告主归属；不能通过任意点击 URL 绕过业务绑定。
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

        $extra = $account['extra'] ?? [];
        if (is_string($extra)) {
            $extra = json_decode($extra, true);
        }
        $payload = $this->callbackPayload($event, $time, $params, is_array($extra) ? $extra : []);

        if ($unauthenticated) {
            $response = $this->requestUnauthenticatedCallback($callbackUrl, $payload);
        } else {
            $credentialId = self::privateCredentialId($appCode);
            $credential = DB::connection('uc')->table('mkt_user as c')
                ->join('mkt_app as d', 'd.app_id', '=', 'c.app_id')
                ->where('c.user_id', $credentialId)->where('d.platform', AdConst::PUT_SOURCE_TX)
                ->first(['c.access_token', 'c.access_time']);
            $credential = is_object($credential) ? (array)$credential : (is_array($credential) ? $credential : []);
            if (empty($credential['access_token']) || (int)($credential['access_time'] ?? 0) <= time() + 60) {
                throw new Exception('腾讯私有应用 Token 缺失或即将过期');
            }
            $response = $this->requestCallback($callbackUrl, $payload, $credential['access_token']);
        }

        $code = $response['code'] ?? -1;
        $code = (is_int($code) || (is_string($code) && ctype_digit($code))) ? (int)$code : -1;
        $traceId = $response['trace_id'] ?? null;
        $traceId = is_string($traceId) && preg_match('/^[a-zA-Z0-9_\\-]{1,128}$/D', $traceId) ? $traceId : null;
        $reason = $response['_reason_code'] ?? '';
        if (!is_string($reason) || !in_array($reason, [
            'CURL_INIT_FAILED', 'TRANSPORT_FAILED', 'HTTP_NON_200', 'INVALID_JSON',
        ], true)) {
            $reason = $code === 0 ? 'SUCCESS' : 'API_REJECTED';
        }
        Log::info('腾讯 DataNexus Callback', [
            'app_code' => $appCode,
            'mode' => $unauthenticated ? 'HTTP_NO_AUTH' : 'HTTPS_AUTH',
            'code' => $code,
            'trace_id' => $traceId,
            'http_status' => (int)($response['_http_status'] ?? 0),
            'reason_code' => $reason,
        ]);
        return $code === 0;
    }

    protected function requestUnauthenticatedCallback(string $url, array $payload): array
    {
        if (!TencentCallbackUrl::isUnauthenticatedPostTarget($url)) {
            throw new Exception('腾讯无鉴权 Callback 目标无效');
        }
        // 不能向 HTTP 添加 access-token、Authorization、cookie 或私有凭证。
        return $this->sendCallbackHttp($url, $payload, [
            'Content-Type: application/json',
            'cache-control: no-cache',
        ], CURLPROTO_HTTP);
    }

    protected function requestCallback(string $url, array $payload, string $accessToken): array
    {
        TencentCallbackUrl::assertPostTarget($url);
        return $this->sendCallbackHttp($url, $payload, [
            'Content-Type: application/json',
            'access-token: ' . $accessToken,
            'timestamp: ' . time(),
            'nonce: ' . bin2hex(random_bytes(16)),
            'cache-control: no-cache',
        ], CURLPROTO_HTTPS);
    }

    /**
     * 只有两个经过严格白名单校验的调用方；协议与 Header 各自隔离。
     * 受保护方法允许无网络的隔离测试检查实际 Header 和 cURL 协议。
     */
    protected function sendCallbackHttp(string $url, array $payload, array $headers, int $protocol): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || strlen($body) > 50 * 1024) {
            throw new Exception('腾讯 Callback 请求体无效或超过 50KB');
        }
        $handle = curl_init($url);
        if ($handle === false) {
            return ['_reason_code' => 'CURL_INIT_FAILED'];
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => $protocol,
            CURLOPT_REDIR_PROTOCOLS => 0,
        ]);
        $raw = curl_exec($handle);
        $httpCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($handle);
        curl_close($handle);
        if ($raw === false) {
            return ['_reason_code' => 'TRANSPORT_FAILED', '_http_status' => $httpCode,
                '_curl_errno' => $curlErrno];
        }
        if ($httpCode !== 200) {
            return ['_reason_code' => 'HTTP_NON_200', '_http_status' => $httpCode];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['_reason_code' => 'INVALID_JSON', '_http_status' => $httpCode];
        }
        $data['_http_status'] = $httpCode;
        return $data;
    }

    private function callbackFailureCode(\Throwable $error): string
    {
        if ($error instanceof \InvalidArgumentException) {
            return 'INVALID_CALLBACK_URL';
        }
        $known = [
            '腾讯广告主 ID 无效' => 'INVALID_ADVERTISER_ID',
            '腾讯广告主与业务 App 未绑定或账户无效' => 'ADVERTISER_UNBOUND',
            '腾讯私有应用 Token 缺失或即将过期' => 'TOKEN_INVALID',
            '腾讯 Callback 缺少有效点击标识或事件时间' => 'INVALID_CLICK_OR_TIME',
            '腾讯 APP 回传缺少设备标识' => 'MISSING_DEVICE_ID',
            '腾讯回传设备标识格式错误' => 'INVALID_DEVICE_ID',
            '腾讯 Callback 请求体无效或超过 50KB' => 'INVALID_PAYLOAD_SIZE',
        ];
        return $known[$error->getMessage()] ?? 'CALLBACK_PRECHECK_FAILED';
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
