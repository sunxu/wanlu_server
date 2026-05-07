<?php

declare(strict_types=1);

namespace App\Utils;

use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Context\Context;

class Log
{
    public static function access(string $level, string $message, array $params)
    {
        return self::get('access')->log($level, $message, self::getMessage($params));
    }

    public static function system(string $level, string $message, array $params)
    {
        return self::get()->log($level, $message, self::getMessage($params));
    }

    public static function sql(string $level, string $message, array $params)
    {
        return self::get('sql')->log($level, $message, self::getMessage($params));
    }

    public static function http(string $level, string $message, array $params)
    {
        return self::get('http')->log($level, $message, self::getMessage($params));
    }

    protected static function get(string $group = 'default', string $name = 'app')
    {
        return ApplicationContext::getContainer()->get(LoggerFactory::class)->get($name, $group);
    }

    protected static function getMessage(array $params): array
    {
        $trace = Context::get('x_trace', create_uuid());
        data_set($params, 'traceId', $trace);

        return $params;
    }
}
