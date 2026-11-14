<?php
declare(strict_types=1);

namespace App\Service\Mkt;

use InvalidArgumentException;

/**
 * 腾讯私有凭证使用业务 app_code 作为唯一归属，不占用腾讯 OAuth 用户 ID。
 * 本规则必须与 api_server TencentService::privateCredentialId 一致。
 */
final class TxPrivateCredentialId
{
    public static function fromAppCode(string $appCode): string
    {
        if (!preg_match('/^[a-z0-9_]{1,50}$/D', $appCode)) {
            throw new InvalidArgumentException('业务 App 标识无效');
        }

        return 'txapp_' . sha1($appCode);
    }

    public static function isPrivate(string $userId): bool
    {
        return (bool)preg_match('/^txapp_[a-f0-9]{40}$/D', $userId);
    }
}
