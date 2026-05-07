<?php

declare(strict_types=1);
use App\Middleware\AccessLogMiddleware;
use App\Middleware\CorsMiddleware;

return [
    'http' => [
        // 数组内配置您的全局中间件，顺序根据该数组的顺序
        AccessLogMiddleware::class,
        CorsMiddleware::class,
    ],
];
