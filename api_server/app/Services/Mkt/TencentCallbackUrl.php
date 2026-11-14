<?php

namespace App\Services\Mkt;

use InvalidArgumentException;

/**
 * DataNexus 的 __CALLBACK__ 是腾讯下发的完整 URL，不允许任意 URL 携带 Token。
 * HTTP tracking.e.qq.com/conv 只允许无 Token POST；仅 api.e.qq.com HTTPS 可鉴权 POST。
 */
final class TencentCallbackUrl
{
    public static function assertClickTarget(string $url): void
    {
        if ($url === '' || strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('腾讯 Callback URL 无效或过长');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || isset($parts['port'])) {
            throw new InvalidArgumentException('腾讯 Callback URL 含不允许的地址部分');
        }
        $host = strtolower((string)($parts['host'] ?? ''));
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $path = (string)($parts['path'] ?? '');
        if (!(($scheme === 'https' && $host === 'api.e.qq.com'
                && in_array($path, ['/conv', '/v3.0/user_actions/add'], true))
            || ($scheme === 'http' && $host === 'tracking.e.qq.com' && $path === '/conv'))) {
            throw new InvalidArgumentException('腾讯 Callback URL 目标不在允许列表');
        }
        parse_str((string)($parts['query'] ?? ''), $query);
        if (!is_string($query['cb'] ?? null) || $query['cb'] === ''
            || !is_string($query['conv_id'] ?? null) || $query['conv_id'] === '') {
            throw new InvalidArgumentException('腾讯 Callback URL 缺少必要参数');
        }
    }


    /**
     * 未开启鉴权时腾讯下发的唯一 HTTP POST 目标。
     * 只返回目标类型，不访问 URL；严禁携带 access-token。
     */
    public static function isUnauthenticatedPostTarget(string $url): bool
    {
        self::assertClickTarget($url);
        $parts = parse_url($url);
        return strtolower((string)$parts['scheme']) === 'http'
            && strtolower((string)$parts['host']) === 'tracking.e.qq.com'
            && (string)$parts['path'] === '/conv';
    }

    public static function assertPostTarget(string $url): void
    {
        self::assertClickTarget($url);
        $parts = parse_url($url);
        if (strtolower((string)$parts['scheme']) !== 'https'
            || strtolower((string)$parts['host']) !== 'api.e.qq.com') {
            throw new InvalidArgumentException('未开启腾讯 HTTPS Callback 鉴权，拒绝发送 Token');
        }
    }
}
