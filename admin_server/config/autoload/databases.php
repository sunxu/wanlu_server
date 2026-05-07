<?php

declare(strict_types=1);
return [
    'default' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', 'localhost'),
        'database' => env('DB_DATABASE', 'hyperf'),
        'port' => env('DB_PORT', 3306),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('DB_PREFIX', ''),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Model',
                'force_casts' => true,
                'inheritance' => 'Model',
                'with_comments' => true
            ],
        ]
    ],
    'test' => [
        'driver' => env('DB_TEST_DRIVER', 'mysql'),
        'host' => env('DB_TEST_HOST', 'localhost'),
        'database' => env('DB_TEST_DATABASE', 'hyperf'),
        'port' => env('DB_TEST_PORT', 3306),
        'username' => env('DB_TEST_USERNAME', 'root'),
        'password' => env('DB_TEST_PASSWORD', ''),
        'charset' => env('DB_TEST_CHARSET', 'utf8mb4'),
        'collation' => env('DB_TEST_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('DB_TEST_PREFIX', ''),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Model',
                'force_casts' => true,
                'inheritance' => 'Model',
                'with_comments' => true
            ],
        ]
    ],
    'mis' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_MIS_HOST', 'localhost'),
        'database' => env('DB_MIS_DATABASE', 'hyperf'),
        'port' => env('DB_MIS_PORT', 3306),
        'username' => env('DB_MIS_USERNAME', 'root'),
        'password' => env('DB_MIS_PASSWORD', ''),
        'charset' => env('DB_MIS_CHARSET', 'utf8mb4'),
        'collation' => env('DB_MIS_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('DB_MIS_PREFIX', ''),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/ModelMis',
                'force_casts' => true,
                'inheritance' => 'Model',
                'with_comments' => true
            ],
        ]
    ],
    'tools' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_TOOLS_HOST', 'localhost'),
        'database' => env('DB_TOOLS_DATABASE', 'hyperf'),
        'port' => env('DB_TOOLS_PORT', 3306),
        'username' => env('DB_TOOLS_USERNAME', 'root'),
        'password' => env('DB_TOOLS_PASSWORD', ''),
        'charset' => env('DB_TOOLS_CHARSET', 'utf8mb4'),
        'collation' => env('DB_TOOLS_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => env('DB_TOOLS_PREFIX', ''),
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float)env('DB_MAX_IDLE_TIME', 60),
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/ModelTools',
                'force_casts' => true,
                'inheritance' => 'Model',
                'with_comments' => true
            ],
        ]
    ]

];
