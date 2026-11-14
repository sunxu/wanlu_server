<?php
declare(strict_types=1);

namespace App\Service\Mkt;

/**
 * Pure owner-binding gate. Reject any ownership collision.
 * Never reassign an existing account's user_id or advertiser's app_code.
 */
final class TencentPrivateBindingGuard
{
    public static function inspect(
        ?array $account,
        ?array $advertiser,
        string $privateUserId,
        string $appCode,
        string $accountId
    ): array {
        if ($account !== null) {
            if ((string)($account['user_id'] ?? '') !== $privateUserId) {
                return ['status' => 'TX_BIND_EXISTING_ACCOUNT_OWNER_HOLD'];
            }
            if ((int)($account['is_valid'] ?? 0) !== 1) {
                return ['status' => 'TX_BIND_EXISTING_ACCOUNT_DISABLED_HOLD'];
            }
        }

        if ($advertiser !== null) {
            if ((string)($advertiser['advertiser_id'] ?? '') !== $accountId
                || (string)($advertiser['account_id'] ?? '') !== $accountId
                || (string)($advertiser['platform'] ?? '') !== 'tx'
                || (string)($advertiser['app_code'] ?? '') !== $appCode) {
                return ['status' => 'TX_BIND_EXISTING_ADVERTISER_OWNER_HOLD'];
            }
            if ((int)($advertiser['status'] ?? 0) !== 1) {
                return ['status' => 'TX_BIND_EXISTING_ADVERTISER_DISABLED_HOLD'];
            }
        }

        $createAccount = $account === null;
        $createAdvertiser = $advertiser === null;
        return [
            'status' => (!$createAccount && !$createAdvertiser)
                ? 'TX_BIND_ALREADY_PRESENT' : 'TX_BIND_READY',
            'create_account' => $createAccount,
            'create_advertiser' => $createAdvertiser,
        ];
    }
}
