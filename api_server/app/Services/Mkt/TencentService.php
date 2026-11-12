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

    public function payload(string $event, int $time, array $params, array $extra): array
    {
        $accountId = (string)($params['account_id'] ?? '');
        $setId = (string)($extra['user_action_set_id'] ?? '');
        $clickId = (string)($params['click_id'] ?? '');
        if (!ctype_digit($accountId) || (int)$accountId <= 0 || !ctype_digit($setId) || (int)$setId <= 0 || $clickId === '' || strlen($clickId) > 64 || $time <= 0) {
            throw new Exception('腾讯回传缺少有效账户、行为源、点击标识或事件时间');
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
                $user[$field] = $key === 'oaid' ? md5($value) : strtolower($value);
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
        if ($type === 'CUSTOM') {
            $custom = $extra['custom_action'] ?? '';
            if (!is_string($custom) || trim($custom) === '' || strlen($custom) > 128) {
                throw new Exception('腾讯 CUSTOM 事件需要配置 custom_action');
            }
            $action['custom_action'] = $custom;
        }
        return ['account_id' => (int)$accountId, 'user_action_set_id' => (int)$setId, 'actions' => [$action]];
    }
}
