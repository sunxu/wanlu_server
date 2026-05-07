<?php
/**
 * 数据库配置
 */
return [
    // 默认启用数据库类型
    'default' => env('DB_CONNECTION', 'mysql'),
    // 数据库连接配置
    'connections' => [
        // MySQL
        'mysql' => [
            // 主库配置
            'write' => [
                'host' => env('DB_HOST_WRITE', '127.0.0.1'),
                'username' => env('DB_USERNAME_WRITE', ''),
                'password' => env('DB_PASSWORD_WRITE', '')
            ],
            // 从库配置
            'read' => [
                [
                    'host' => env('DB_HOST_READ', '127.0.0.1'),
                    'username' => env('DB_USERNAME_READ', ''),
                    'password' => env('DB_PASSWORD_READ', '')
                ],
//                [
//                    'host' => env('DB_HOST_READ1', '127.0.0.1'),
//                    'username' => env('DB_USERNAME_READ', ''),
//                    'password' => env('DB_PASSWORD_READ', '')
//                ]
            ],
            // 通用配置
            'driver' => 'mysql',
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'test'),
            'charset' => env('DB_CHARSET', 'utf8'),
            'collation' => env('DB_COLLATION', 'utf8_general_ci'),
            'prefix' => env('DB_PREFIX', ''),
            'timezone' => env('DB_TIMEZONE', '+08:00'),
            'sticky' => true,
            'strict' => env('DB_STRICT_MODE', false)
        ],
        // product
        'product' => [
            'host' => env('DB_HOST_PRO', '127.0.0.1'),
            'username' => env('DB_USERNAME_PRO'),
            'password' => env('DB_PASSWORD_PRO'),
            'database' => env('DB_DATABASE_PRO'),
            'driver' => 'mysql',
            'port' => env('DB_PORT', 3306),
            'charset' => env('DB_CHARSET', 'utf8'),
            'collation' => env('DB_COLLATION', 'utf8_general_ci'),
            'prefix' => env('DB_PREFIX', ''),
            'timezone' => env('DB_TIMEZONE', '+08:00'),
            'strict' => env('DB_STRICT_MODE', false)
        ],
        // 后台
        'uc' => [
            'host' => env('DB_HOST_UC', '127.0.0.1'),
            'username' => env('DB_USERNAME_UC'),
            'password' => env('DB_PASSWORD_UC'),
            'database' => env('DB_DATABASE_UC'),
            'driver' => 'mysql',
            'port' => env('DB_PORT', 3306),
            'charset' => env('DB_CHARSET', 'utf8'),
            'collation' => env('DB_COLLATION', 'utf8_general_ci'),
            'prefix' => env('DB_PREFIX', ''),
            'timezone' => env('DB_TIMEZONE', '+08:00'),
            'strict' => env('DB_STRICT_MODE', false)
        ]
    ],
    // Redis
    'redis' => [
        // 关闭分布式
        'cluster' => env('REDIS_CLUSTER', false),
        // 默认配置
        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_DATABASE', 10)
        ],
        // 缓存配置
        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_CACHE_DB', 11)
        ],
        // 自然日留存
        'active' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_ACTIVE_DATABASE', 8)
        ],
        // 广告
        'ad' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_AD_DATABASE', 9)
        ],
        // 设备列表黑名单
        'device' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_AD_DATABASE', 7)
        ],
        // 激活留存
        'keep' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_AD_DATABASE', 6)
        ],
        //广告打点
        'event' => [
            'host' => env('REDIS_EVENT_AD_HOST', '127.0.0.1'),
            'port' => env('REDIS_EVENT_AD_PORT', 6379),
            'password' => env('REDIS_EVENT_AD_PASSWORD', null),
            'database' => env('REDIS_EVENT_AD_DATABASE', 0)
        ]
    ]
];
