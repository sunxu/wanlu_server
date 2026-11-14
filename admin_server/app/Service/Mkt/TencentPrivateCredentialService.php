<?php
declare(strict_types=1);

namespace App\Service\Mkt;

use App\Constants\MktConst;
use App\Library\Mkt\MktTx;
use App\Model\App;
use App\Model\Mkt\MktApp;
use App\Model\Mkt\MktUser;
use Hyperf\DbConnection\Db;

/**
 * 腾讯私有凭证：每个业务 App 对应一条确定性 mkt_user。
 * 导入时主动刷新一次，使用腾讯 API 返回的 TTL，不接受手工填写的到期时间。
 */
class TencentPrivateCredentialService
{
    public function import(string $appCode, array $input): string
    {
        try {
            $userId = TxPrivateCredentialId::fromAppCode($appCode);
        } catch (\InvalidArgumentException $e) {
            throw new TencentPrivateImportError(TencentPrivateImportError::INVALID_INPUT);
        }
        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (!isset($input[$key]) || !is_string($input[$key])) {
                throw new TencentPrivateImportError(TencentPrivateImportError::INVALID_INPUT);
            }
        }
        if (array_key_exists('replace_existing', $input) && !is_bool($input['replace_existing'])) {
            throw new TencentPrivateImportError(TencentPrivateImportError::INVALID_INPUT);
        }
        $clientId = $input['client_id'];
        $secret = $input['client_secret'];
        $refresh = $input['refresh_token'];
        $replace = $input['replace_existing'] ?? false;

        if (!preg_match('/^[0-9]{1,50}$/D', $clientId)
            || $secret === ''
            || $refresh === '' || strlen($refresh) > 500) {
            throw new TencentPrivateImportError(TencentPrivateImportError::INVALID_INPUT);
        }
        // mkt_app.app_secret 当前数据库字段为 VARCHAR(100)，不能按 API 的 256 字节上限盲目写入。
        if (strlen($secret) > 100) {
            throw new TencentPrivateImportError(TencentPrivateImportError::SECRET_STORAGE_LIMIT);
        }
        try {
            if (!App::query()->where('app_code', $appCode)->where('status', 1)->exists()) {
                throw new TencentPrivateImportError(TencentPrivateImportError::APP_NOT_ACTIVE);
            }
    
            // 调用腾讯刷新接口之前先检查归属，防止无意义刷新和替换。
            $developer = MktApp::find($clientId);
            if ($developer && ($developer->platform !== MktConst::PLATFORM_TX
                    || !hash_equals((string)$developer->app_secret, $secret))) {
                throw new TencentPrivateImportError(TencentPrivateImportError::DEVELOPER_APP_CONFLICT);
            }
            $existing = MktUser::find($userId);
            if ($existing && ((string)$existing->app_id !== $clientId || !$replace)) {
                throw new TencentPrivateImportError(TencentPrivateImportError::CREDENTIAL_ALREADY_EXISTS);
            }
            foreach (MktUser::query()->where('app_id', $clientId)->get(['user_id', 'refresh_token']) as $other) {
                if ((string)$other->user_id !== $userId
                    && hash_equals((string)$other->refresh_token, $refresh)) {
                    throw new TencentPrivateImportError(TencentPrivateImportError::TOKEN_ALREADY_BOUND);
                }
            }
    
        } catch (TencentPrivateImportError $e) {
            throw $e;
        } catch (\Throwable $e) {
            // 数据库连接/字段/SQL 错误可能含敏感信息；仅返回固定分类。
            throw new TencentPrivateImportError(TencentPrivateImportError::DB_READ_FAILED);
        }

        // 刷新成功同时验证 Refresh Token 可以使用，取得腾讯实际 Access Token 与剩余寿命。
        // 腾讯新生成的私有 Token 可能需要约 10 分钟生效，尚未生效时不进行任何写入。
        try {
            $token = $this->refreshToken($clientId, $secret, $refresh);
        } catch (\Throwable $e) {
            // MktTx 自身已屏蔽 URL 中的 Token；这里只输出固定原因代码。
            $reason = strpos($e->getMessage(), '腾讯营销请求失败：') === 0
                ? TencentPrivateImportError::TENCENT_NETWORK_FAILED
                : TencentPrivateImportError::TENCENT_REFRESH_REJECTED;
            throw new TencentPrivateImportError($reason);
        }
        if (!is_array($token)) {
            throw new TencentPrivateImportError(TencentPrivateImportError::TENCENT_TOKEN_RESPONSE_INVALID);
        }
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
            throw new TencentPrivateImportError(TencentPrivateImportError::TENCENT_TOKEN_RESPONSE_INVALID);
        }

        try {
            Db::transaction(function () use (
                $appCode, $userId, $clientId, $secret, $refresh, $replace,
                $access, $nextRefresh, $accessExpiry, $refreshExpiry
            ) {
            $developer = MktApp::query()->where('app_id', $clientId)->lockForUpdate()->first();
            if ($developer && ($developer->platform !== MktConst::PLATFORM_TX
                || !hash_equals((string)$developer->app_secret, $secret))) {
                throw new TencentPrivateImportError(TencentPrivateImportError::DEVELOPER_APP_CONFLICT);
            }
            $credential = MktUser::query()->where('user_id', $userId)->lockForUpdate()->first();
            if ($credential && ((string)$credential->app_id !== $clientId || !$replace)) {
                throw new TencentPrivateImportError(TencentPrivateImportError::CREDENTIAL_ALREADY_EXISTS);
            }
            foreach (MktUser::query()->where('app_id', $clientId)
                ->get(['user_id', 'access_token', 'refresh_token']) as $other) {
                if ((string)$other->user_id !== $userId &&
                    (hash_equals((string)$other->refresh_token, $refresh)
                    || hash_equals((string)$other->refresh_token, $nextRefresh)
                    || hash_equals((string)$other->access_token, $access))) {
                    throw new TencentPrivateImportError(TencentPrivateImportError::TOKEN_ALREADY_BOUND);
                }
            }

            if (!$developer) {
                $developer = new MktApp();
                $developer->app_id = $clientId;
                $developer->app_name = '腾讯私有应用';
                $developer->app_secret = $secret;
                $developer->platform = MktConst::PLATFORM_TX;
                $developer->auth_url = '';
                $developer->login_account = '';
                $developer->save();
            }
            if (!$credential) {
                $credential = new MktUser();
                $credential->user_id = $userId;
                $credential->user_name = 'TX:' . substr($appCode, 0, 46);
                $credential->user_email = '';
                $credential->app_id = $clientId;
            }
            $credential->access_token = $access;
            $credential->access_time = $accessExpiry;
            $credential->refresh_token = $nextRefresh;
            $credential->refresh_time = $refreshExpiry;
            $credential->save();
            });
        } catch (TencentPrivateImportError $e) {
            throw $e;
        } catch (\Throwable $e) {
            // SQL 错误可能包含原始 Token 值，禁止暴露异常文本或 previous。
            throw new TencentPrivateImportError(TencentPrivateImportError::DB_WRITE_FAILED);
        }
        return $userId;
    }

    /** 便于离线测试替换接口；正式环境通过现有 MktTx 实现。 */
    protected function refreshToken(string $clientId, string $secret, string $refreshToken): array
    {
        return (new MktTx())->setApp($clientId, $secret)->authRefreshToken($refreshToken);
    }
}
