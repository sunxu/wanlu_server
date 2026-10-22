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

    public function authorize(string $appId): string
    {
        $app = MktApp::find($appId);
        if (!$app || $app->platform !== MktConst::PLATFORM_TX) {
            throw new Exception('腾讯开放平台应用不存在');
        }
        $redirectUri = make(MktTx::class)->setAppInfo($app->toArray())->redirectUri();
        $state = bin2hex(random_bytes(32));
        RedisManager::get()->setex('mkt:tx:oauth:' . $state, 600, $appId);
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
        if (!$appId) {
            throw new Exception('腾讯授权请求已过期或已使用，请重新授权');
        }
        make(MktService::class)->authUser((string)$appId, $code);
    }
}
