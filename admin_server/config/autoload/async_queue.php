<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

return [
    'default' => [
        'driver' => \Hyperf\AsyncQueue\Driver\RedisDriver::class,
        'redis' => [
            'pool' => 'default',
        ],
        'channel' => 'default',
        'timeout' => 30,
        'retry_seconds' => 1,//失败后重新尝试间隔
        'handle_timeout' => 60,
        'processes' => 1,
        'concurrent' => [
            'limit' => 5,
        ],
    ],
    \App\Constants\MktConst::QUEUE_KEY => [
        'driver' => \Hyperf\AsyncQueue\Driver\RedisDriver::class,
        'redis' => [
            'pool' => 'queue',
        ],
        'channel' => 'mkt',
        'timeout' => 30,
        'retry_seconds' => [30, 60],//失败后重新尝试间隔
        'handle_timeout' => 1800,
        'processes' => 1,
        'concurrent' => [
            'limit' => 5,
        ],
    ],
    'task' => [
        'driver' => \Hyperf\AsyncQueue\Driver\RedisDriver::class,
        'redis' => [
            'pool' => 'queue',
        ],
        'channel' => 'task',
        'timeout' => 30,
        'retry_seconds' => [60],//失败后重新尝试间隔
        'handle_timeout' => 1800,
        'processes' => 1,
        'concurrent' => [
            'limit' => 3,
        ],
    ]
];
