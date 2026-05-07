<?php

declare(strict_types=1);

$prefix = env('APP_NAME', 'uc') . ':' . env('APP_ENV', 'dev');
$prefix_mis = 'mis' . ':' . env('APP_ENV', 'dev');

return [
    'default' => [
        'host' => env('REDIS_HOST', 'localhost'),
        'auth' => env('REDIS_AUTH', null),
        'port' => (int) env('REDIS_PORT', 6379),
        'db' => (int) env('REDIS_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('REDIS_MAX_IDLE_TIME', 60),
        ],
        'options' => [
            \Redis::OPT_PREFIX => $prefix . ':cache:'
        ]
    ],
    'model_cache' => [//模型缓存
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_AUTH', null),
        'port' => (int)env('REDIS_PORT', 6379),
        'db' => (int)env('REDIS_DB', 0),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('REDIS_MAX_IDLE_TIME', 60),
        ]
    ],
    \App\Constants\RedisConst::POOL_NAME_MIS_USER => [//网赚用户相关
        'host' => env('REDIS_MIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_MIS_AUTH', null),
        'port' => (int)env('REDIS_MIS_PORT', 6379),
        'db' => (int)env('REDIS_DB_MIS_USER', 13),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('REDIS_MAX_IDLE_TIME', 60),
        ]
    ],
    'queue' => [//异步消息队列
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_AUTH', null),
        'port' => (int)env('REDIS_PORT', 6379),
        'db' => (int)env('REDIS_DB_QUEUE', 2),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('REDIS_MAX_IDLE_TIME', 60),
        ],
        'options' => [
            \Redis::OPT_PREFIX => $prefix . ':queue:'
        ]
    ],
    \App\Constants\RedisConst::POOL_NAME_MIS_AD => [//网赚广告相关
        'host' => env('REDIS_MIS_HOST', '127.0.0.1'),
        'auth' => env('REDIS_MIS_AUTH', null),
        'port' => (int)env('REDIS_MIS_PORT', 6379),
        'db' => (int)env('REDIS_DB_MIS_AD', 11),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('REDIS_MAX_IDLE_TIME', 60),
        ],
        'options' => [
            \Redis::OPT_PREFIX => $prefix_mis . ':ad:'
        ]
    ],
];
