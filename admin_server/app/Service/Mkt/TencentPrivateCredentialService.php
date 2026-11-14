<?php
declare(strict_types=1);

namespace App\Service\Mkt;

use App\Constants\MktConst;
use App\Model\App;
use App\Model\Mkt\MktApp;
use App\Model\Mkt\MktUser;
use Hyperf\DbConnection\Db;
use RuntimeException;

/**
 * 私有应用凭证导入：只写 mkt_app 和 mkt_user；不伪造 OAuth 用户或管理账户。
 * 调用方只能从安全的服务器本地文件读取明文，不得把参数记入日志。
 */
class TencentPrivateCredentialService
{
    public function import(string $appCode, array $input): string
    {
        $userId = TxPrivateCredentialId::fromAppCode($appCode);
        $clientId = (string)($input['client_id'] ?? '');
        $secret = (string)($input['client_secret'] ?? '');
        $access = (string)($input['access_token'] ?? '');
        $refresh = (string)($input['refresh_token'] ?? '');
        $accessExpiry = $input['access_expires_at'] ?? null;
        $refreshExpiry = $input['refresh_expires_at'] ?? null;
        $now = time();

        foreach (['client_id', 'client_secret', 'access_token', 'refresh_token', 'app_name'] as $key) {
            if (array_key_exists($key, $input) && !is_string($input[$key])) {
                throw new RuntimeException('腾讯私有应用凭证字段类型错误');
            }
        }
        if (array_key_exists('replace_existing', $input) && !is_bool($input['replace_existing'])) {
            throw new RuntimeException('replace_existing 必须是布尔值');
        }

        if (!preg_match('/^[0-9]{1,50}$/D', $clientId)
            || strlen($secret) > 100
            || $access === '' || strlen($access) > 500
            || $refresh === '' || strlen($refresh) > 500
            || !is_int($accessExpiry) || !is_int($refreshExpiry)
            || $accessExpiry <= $now + 60 || $refreshExpiry <= $now + 60
            || $accessExpiry > 4294967295 || $refreshExpiry > 4294967295
            || $refreshExpiry <= $accessExpiry) {
            throw new RuntimeException('腾讯私有应用凭证字段或真实到期时间无效');
        }
        if (!App::query()->where('app_code', $appCode)->where('status', 1)->exists()) {
            throw new RuntimeException('业务 App 不存在或已停用');
        }

        Db::transaction(function () use (
            $appCode, $input, $userId, $clientId, $secret, $access, $refresh, $accessExpiry, $refreshExpiry
        ) {
            $app = MktApp::query()->where('app_id', $clientId)->lockForUpdate()->first();
            if (!$app && $secret === '') {
                throw new RuntimeException('首次登记开发者应用必须提供 client_secret');
            }
            if ($app && $app->platform !== MktConst::PLATFORM_TX) {
                throw new RuntimeException('开发者应用 ID 已被其他投放平台使用');
            }
            if ($app && $secret !== '' && !hash_equals((string)$app->app_secret, $secret)) {
                throw new RuntimeException('client_secret 与已登记密钥不符；请先走单独的密钥轮换流程');
            }

            $credential = MktUser::query()->where('user_id', $userId)->lockForUpdate()->first();
            if ($credential && (string)$credential->app_id !== $clientId) {
                throw new RuntimeException('业务 App 已绑定另一开发者应用，拒绝隐式迁移');
            }
            if ($credential && !($input['replace_existing'] ?? false)) {
                throw new RuntimeException('已有私有凭证；替换须明确设置 replace_existing=true');
            }

            // 一个业务 App 一套 Token；禁止误把同一组凭证导入两个业务 App。
            foreach (MktUser::query()->where('app_id', $clientId)->get(['user_id', 'access_token', 'refresh_token']) as $other) {
                if ((string)$other->user_id !== $userId
                    && (hash_equals((string)$other->access_token, $access)
                        || hash_equals((string)$other->refresh_token, $refresh))) {
                    throw new RuntimeException('同一腾讯凭证已分配给另一业务 App');
                }
            }

            if (!$app) {
                $app = new MktApp();
                $app->app_id = $clientId;
                $app->app_name = mb_substr((string)($input['app_name'] ?? '腾讯私有应用'), 0, 50);
                $app->app_secret = $secret;
                $app->platform = MktConst::PLATFORM_TX;
                $app->auth_url = '';
                $app->login_account = '';
                $app->save();
            }
            if (!$credential) {
                $credential = new MktUser();
                $credential->user_id = $userId;
                $credential->user_name = mb_substr('腾讯私有凭证 ' . $appCode, 0, 50);
                $credential->user_email = '';
                $credential->app_id = $clientId;
            }
            $credential->access_token = $access;
            $credential->access_time = $accessExpiry;
            $credential->refresh_token = $refresh;
            $credential->refresh_time = $refreshExpiry;
            $credential->save();
        });

        return $userId;
    }
}
