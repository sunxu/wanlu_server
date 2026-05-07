<?php
//
require_once __DIR__ . '/../vendor/autoload.php';
(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(dirname(__DIR__)))->bootstrap();

// 创建应用
$app = new Laravel\Lumen\Application(dirname(__DIR__));

// 门面模式
$app->withFacades();

// ORM
// $app->withEloquent();

// 容器绑定
$app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, App\Exceptions\Handler::class);
$app->singleton(Illuminate\Contracts\Console\Kernel::class, App\Console\Kernel::class);

// 全局中间件
$app->middleware([]);

// 路由中间件
$app->routeMiddleware([
    'ApiAuth' => App\Http\Middleware\ApiAuth::class,
]);

// 注册服务
$app->register(App\Providers\AppServiceProvider::class);
$app->register(App\Providers\EventServiceProvider::class);
$app->register(Illuminate\Redis\RedisServiceProvider::class);

// 加载路由
$app->router->group([
    'namespace' => 'App\Http\Controllers'
], function ($router) {
    require __DIR__ . '/../routes/v1/api.php';
});

// 应用配置
$app->configure('app');
$app->configure('database');
$app->configure('error');
$app->configure('api');
$app->configure('ad');
$app->configure('ding');
$app->configure('queue');
$app->configure('aliyun');
$app->configure('amap');
$app->configure('wechatpay');
$app->configure('appconf');
$app->configure('volcengine');
// 测试用例配置
$app->configure('tests/app');

// 返回实例
return $app;
