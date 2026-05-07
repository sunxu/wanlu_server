<?php

declare(strict_types=1);

namespace App\Utils;

use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;

class RedisManager
{
    public static function get(string $config = 'default'): Redis
    {
        $container = ApplicationContext::getContainer();

        // 通过 DI 容器获取或直接注入 RedisFactory 类
        return $container->get(RedisFactory::class)->get($config);
    }
}
