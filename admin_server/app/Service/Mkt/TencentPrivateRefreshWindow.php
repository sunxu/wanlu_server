<?php
declare(strict_types=1);

namespace App\Service\Mkt;

/**
 * Refresh policy only: private Tencent tokens are refreshed one day early,
 * legacy platforms retain their 20-minute refresh lead.
 * No network, logging or database access.
 */
final class TencentPrivateRefreshWindow
{
    public const LEGACY_LEAD_SECONDS = 20 * 60;
    public const PRIVATE_LEAD_SECONDS = 24 * 60 * 60;

    public static function shouldEnqueue(
        string $userId,
        int $accessTime,
        int $refreshTime,
        int $now
    ): bool {
        if (!TxPrivateCredentialId::isPrivate($userId)) {
            return $accessTime < $now + self::LEGACY_LEAD_SECONDS;
        }
        return $accessTime < $now + self::PRIVATE_LEAD_SECONDS
            || ($refreshTime > 0 && $refreshTime < $now + self::PRIVATE_LEAD_SECONDS);
    }

    public static function mergeCandidates(array $legacyDue, array $privateCandidates): array
    {
        $result = [];
        foreach ($legacyDue as $userId) {
            $id = (string)$userId;
            $result[$id] = $id;
        }
        foreach ($privateCandidates as $userId) {
            $id = (string)$userId;
            // LIKE prefixes can match extra rows; verify full deterministic ID.
            if (TxPrivateCredentialId::isPrivate($id)) {
                $result[$id] = $id;
            }
        }
        return array_values($result);
    }
}
