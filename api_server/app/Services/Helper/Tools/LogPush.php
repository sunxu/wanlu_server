<?php
/**
 * 日志推送
 */

namespace App\Services\Helper\Tools;

class LogPush
{
    /**
     * 错误日志记录并推送
     * @param string $message
     * @param array $context
     */
    public static function error(string $message, array $context = [])
    {
        \Log::error($message, $context);
        if (env('APP_ENV') === 'production') {
            Dding::push(json_encode([
                'message' => $message,
                'content' => $context
            ], 320));
        }
    }

    /**
     * 钉钉推送
     * @param string $message
     * @param array $context
     */
    public static function dding(string $message, array $context = [])
    {
        if (env('APP_ENV') === 'production') {
            Dding::push(json_encode([
                'message' => $message,
                'content' => $context
            ], 320));
        }
    }
}
