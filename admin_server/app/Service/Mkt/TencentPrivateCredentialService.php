<?php
declare(strict_types=1);

namespace App\Service\Mkt;

use App\Constants\MktConst;
use App\Library\Mkt\MktTx;
use App\Model\App;
use App\Model\Mkt\MktApp;
use App\Model\Mkt\MktUser;
use Hyperf\DbConnection\Db;
use RuntimeException;

/**
 * 腾讯私有凭证：每个业务 App 对应一条确定性 mkt_user。
 * 导入时主动刷新一次，使用腾讯 API 返回的 TTL，不接受手工填写的到期时间。
 */
class TencentPrivateCredentialService
{
    public function import(string $appCode, array $input): string
    {
        $userId = TxPrivateCredentialId::fromAppCode($appCode);
        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (!isset($input[$key]) || !is_string($input[$key])) {
                throw new RuntimeException('腾讯凭证参数缺失或类型错误');
            }
        }
        if (array_key_exists('replace_existing', $input) && !is_bool($input['replace_existing'])) {
            throw new RuntimeException('replace_existing 必须为布尔值');
        }
        $clientId = $input['client_id'];
        $secret = $input['client_secret'];
        $refresh = $input['refresh_token'];
        $replace = $input['replace_existing'] ?? false;

        if (!preg_match('/^[0-9]{1,50}$/D', $clientId)
            || $secret === '' || strlen($secret) > 100
            || $refresh === '' || strlen($refresh) > 500) {
            throw new RuntimeException('腾讯开发者应用或 Refresh Token 无效');
        }
        if (!App::query()->where('app_code', $appCode)->where('status', 1)->exists()) {
            throw new RuntimeException('业务 App 不存在或已停用');
        }

        // 调用腾讯刷新接口之前先检查归属，防止无意义刷新和替换。
        $developer = MktApp::find($clientId);
        if ($developer && ($developer->platform !== MktConst::PLATFORM_TX
                || !hash_equals((string)$developer->app_secret, $secret))) {
            throw new RuntimeException('开发者应用信息与已登记记录不一致');
        }
        $existing = MktUser::find($userId);
        if ($existing && ((string)$existing->app_id !== $clientId || !$replace)) {
            throw new RuntimeException('业务 App 已有凭证；替换须指定 --replace，不能隐式修改 client_id');
        }
        foreach (MktUser::query()->where('app_id', $clientId)->get(['user_id', 'refresh_token']) as $other) {
            if ((string)$other->user_id !== $userId
                && hash_equals((string)$other->refresh_token, $refresh)) {
                throw new RuntimeException('Refresh Token 已绑定另一业务 App');
            }
        }

        // 刷新成功同时验证 Refresh Token 可以使用，取得腾讯实际 Access Token 与剩余寿命。
        // 腾讯新生成的私有 Token 可能需要约 10 分钟生效，尚未生效时不进行任何写入。
        $token = $this->refreshToken($clientId, $secret, $refresh);
        $now = time();
        $access = (string)($token['access_token'] ?? '');
        $nextRefresh = (string)($token['refresh_token'] ?? $refresh);
        $accessExpiry = $token['access_time'] ?? null;
        // 0 代表腾讯本次响应未返回 Refresh Token 到期时间（未知），绝非已过期。
        $refreshExpiry = $token['refresh_time'] ?? 0;
        if ($access === '' || strlen($access) > 500
            || $nextRefresh === '' || strlen($nextRefresh) > 500
            || !is_int($accessExpiry) || $accessExpiry <= $now + 60
            || $accessExpiry > 4294967295
            || !is_int($refreshExpiry) || $refreshExpiry < 0
            || $refreshExpiry > 4294967295
            || ($refreshExpiry > 0 && ($refreshExpiry <= $now + 60 || $refreshExpiry <= $accessExpiry))) {
            throw new RuntimeException('腾讯刷新接口未返回可信的 Token 有效期');
        }

        Db::transaction(function () use (
            $appCode, $userId, $clientId, $secret, $refresh, $replace,
            $access, $nextRefresh, $accessExpiry, $refreshExpiry
        ) {
            $developer = MktApp::query()->where('app_id', $clientId)->lockForUpdate()->first();
            if ($developer && ($developer->platform !== MktConst::PLATFORM_TX
                || !hash_equals((string)$developer->app_secret, $secret))) {
                throw new RuntimeException('开发者应用归属或密钥已发生变化');
            }
            $credential = MktUser::query()->where('user_id', $userId)->lockForUpdate()->first();
            if ($credential && ((string)$credential->app_id !== $clientId || !$replace)) {
                throw new RuntimeException('现有凭证不允许隐式覆盖或迁移');
            }
            foreach (MktUser::query()->where('app_id', $clientId)
                ->get(['user_id', 'access_token', 'refresh_token']) as $other) {
                if ((string)$other->user_id !== $userId &&
                    (hash_equals((string)$other->refresh_token, $refresh)
                    || hash_equals((string)$other->refresh_token, $nextRefresh)
                    || hash_equals((string)$other->access_token, $access))) {
                    throw new RuntimeException('同一腾讯 Token 已绑定另一业务 App');
                }
            }

            if (!$developer) {
                $developer = new MktApp();
                $developer->app_id = $clientId;
                $developer->app_name = '腾讯私有应用 ' . $clientId;
                $developer->app_secret = $secret;
                $developer->platform = MktConst::PLATFORM_TX;
                $developer->auth_url = '';
                $developer->login_account = '';
                $developer->save();
            }
            if (!$credential) {
                $credential = new MktUser();
                $credential->user_id = $userId;
                $credential->user_name = '腾讯私有凭证 ' . $appCode;
                $credential->user_email = '';
                $credential->app_id = $clientId;
            }
            $credential->access_token = $access;
            $credential->access_time = $accessExpiry;
            $credential->refresh_token = $nextRefresh;
            $credential->refresh_time = $refreshExpiry;
            $credential->save();
        });
        return $userId;
    }

    /** 便于离线测试替换接口；正式环境通过现有 MktTx 实现。 */
    protected function refreshToken(string $clientId, string $secret, string $refreshToken): array
    {
        return (new MktTx())->setApp($clientId, $secret)->authRefreshToken($refreshToken);
    }
}
