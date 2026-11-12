<?php

declare(strict_types=1);

namespace App\Service\Mkt;

use App\Constants\MktConst;
use App\Library\Mkt\MktTx;
use App\Model\Mkt\MktApp;
use App\Utils\RedisManager;
use Exception;

class TencentAuthService
{
    public function applications(): array
    {
        return MktApp::query()->where('platform', MktConst::PLATFORM_TX)->get(['app_id', 'app_name'])->toArray();
    }

    public function authorize(string $appId, string $operatorId): string
    {
        if (!ctype_digit($operatorId) || (int)$operatorId <= 0) {
            throw new Exception('请先登录后台再发起腾讯授权');
        }
        $app = MktApp::find($appId);
        if (!$app || $app->platform !== MktConst::PLATFORM_TX) {
            throw new Exception('腾讯开放平台应用不存在');
        }
        $redirectUri = make(MktTx::class)->setAppInfo($app->toArray())->redirectUri();
        $state = bin2hex(random_bytes(32));
        RedisManager::get()->setex('mkt:tx:oauth:' . $state, 600, json_encode(['app_id' => $appId, 'operator_id' => $operatorId]));
        return 'https://developers.e.qq.com/oauth/authorize?' . http_build_query([
            'client_id' => $appId, 'redirect_uri' => $redirectUri,
            'state' => $state, 'scope' => 'ads_management,ads_insights,account_management,user_actions',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function callback(string $state, string $code): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $state) || $code === '' || strlen($code) > 64) {
            throw new Exception('腾讯授权回调参数无效');
        }
        // GET + DEL 必须原子执行，防止并发回调重放。
        $appId = RedisManager::get()->eval("local v = redis.call('GET', KEYS[1]); if v then redis.call('DEL', KEYS[1]); end; return v", ['mkt:tx:oauth:' . $state], 1);
        $original = json_decode((string)$appId, true);
        if (!is_array($original) || empty($original['app_id']) || empty($original['operator_id'])) {
            throw new Exception('腾讯授权请求已过期或已使用，请重新授权');
        }
        // 腾讯公开 GET 回调不具备后台登录态，仅暂存授权码。
        // 授权关系只能由发起授权的登录用户经受保护接口完成。
        RedisManager::get()->setex('mkt:tx:oauth:pending:' . $state, 600, json_encode([
            'app_id' => (string)$original['app_id'],
            'operator_id' => (string)$original['operator_id'],
            'code' => $code,
        ]));
    }

    public function complete(string $state, string $operatorId): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $state) || !ctype_digit($operatorId) || (int)$operatorId <= 0) {
            throw new Exception('腾讯授权确认参数无效');
        }
        $redis = RedisManager::get();
        $key = 'mkt:tx:oauth:pending:' . $state;
        $value = $redis->get($key);
        if (!$value) {
            if ($redis->get('mkt:tx:oauth:' . $state)) {
                return false; // 腾讯尚未完成回调，允许前端继续等待。
            }
            throw new Exception('腾讯授权已过期或已确认，请重新授权');
        }
        $pending = json_decode((string)$value, true);
        if (!is_array($pending) || !hash_equals((string)($pending['operator_id'] ?? ''), $operatorId)) {
            throw new Exception('腾讯授权仅允许原发起用户确认');
        }
        // 比较并删除必须原子执行，不能让两次确认同时兑换同一授权码。
        $used = $redis->eval("local v = redis.call('GET', KEYS[1]); if v == ARGV[1] then redis.call('DEL', KEYS[1]); return v; end; return false", [$key, (string)$value], 1);
        if (!$used) {
            throw new Exception('腾讯授权已被其他请求确认，请重新授权');
        }
        make(MktService::class)->authUser((string)$pending['app_id'], (string)$pending['code']);
        return true;
    }
}
