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
        // 原子地将一次性 state 转移到待确认区，避免前端轮询看到短暂空窗。
        // 腾讯公开回调不具备后台登录态，不得在这里交换令牌。
        $script = "local v = redis.call('GET', KEYS[1]); if not v then return false; end; "
            . "local p = cjson.decode(v); p.code = ARGV[1]; "
            . "redis.call('SETEX', KEYS[2], 600, cjson.encode(p)); "
            . "redis.call('DEL', KEYS[1]); return 1";
        $moved = RedisManager::get()->eval(
            $script, ['mkt:tx:oauth:' . $state, 'mkt:tx:oauth:pending:' . $state, $code], 2
        );
        if (!$moved) {
            throw new Exception('腾讯授权请求已过期或已使用，请重新授权');
        }
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
