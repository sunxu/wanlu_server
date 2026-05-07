<?php

declare(strict_types=1);
return [
    'default' => [
        'handler' => [
            'class' => Monolog\Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => BASE_PATH . '/runtime/logs/system.log',
                'level' => Monolog\Logger::INFO,
            ],
        ],
        'formatter' => [
            'class' => Monolog\Formatter\JsonFormatter::class,
            'constructor' => [
                'format' => null,
                'batchMode' => Monolog\Formatter\JsonFormatter::BATCH_MODE_JSON,
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    'access' => [
        'handler' => [
            'class' => Monolog\Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => BASE_PATH . '/runtime/logs/access.log',
                'level' => Monolog\Logger::INFO,
            ],
        ],
        'formatter' => [
            'class' => Monolog\Formatter\JsonFormatter::class,
            'constructor' => [
                'format' => null,
                'batchMode' => Monolog\Formatter\JsonFormatter::BATCH_MODE_JSON,
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    'sql' => [
        'handler' => [
            'class' => Monolog\Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => BASE_PATH . '/runtime/logs/sql.log',
                'level' => Monolog\Logger::INFO,
            ],
        ],
        'formatter' => [
            'class' => Monolog\Formatter\JsonFormatter::class,
            'constructor' => [
                'format' => null,
                'batchMode' => Monolog\Formatter\JsonFormatter::BATCH_MODE_JSON,
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    'http' => [
        'handler' => [
            'class' => Monolog\Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => BASE_PATH . '/runtime/logs/http.log',
                'level' => Monolog\Logger::INFO,
            ],
        ],
        'formatter' => [
            'class' => Monolog\Formatter\JsonFormatter::class,
            'constructor' => [
                'format' => null,
                'batchMode' => Monolog\Formatter\JsonFormatter::BATCH_MODE_JSON,
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ]
];
