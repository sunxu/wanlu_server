<?php
/**
 * 路由
 * @var Laravel\Lumen\Routing\Router $router
 */

$router->group([
    // 前缀
    'prefix' => 'v1/',
    // 中间件
//    'middleware' => ''
], function () use ($router) {
    $router->get('handle', 'V1\TestController@handle');
    $router->post('wechat/callback/{appCode}', 'V1\Paid\CallbackController@wechat');

    // 新版监测地址
    $router->get('ad/jl/{appCode}', 'V1\AdController@jl');
    $router->get('ad/ks/{appCode}', 'V1\AdController@ks');
    $router->get('ad/op/{appCode}', 'V1\AdController@op');
    $router->get('ad/hw/{appCode}', 'V1\AdController@hw');
    $router->get('ad/vi/{appCode}', 'V1\AdController@vi');
    $router->get('ad/bd/{appCode}', 'V1\AdController@bd');
    $router->get('ad/tx/{appCode}', 'V1\AdController@tx');
    $router->get('ad/honor/{appCode}', 'V1\AdController@honor');
});

// 权限认证
$router->group([
    // 前缀
    'prefix' => 'v1/',
    // 中间件
    'middleware' => 'ApiAuth'
], function () use ($router) {
    // APP策略
    $router->post('api/config', 'V1\ApiController@config');
    $router->post('api/a', 'V1\ApiController@config');
    $router->post('event/ad', 'V1\EventController@ad');
    $router->post('event/dialog', 'V1\EventController@dialog');
    $router->post('event/information', 'V1\EventController@information');
    $router->post('event/active', 'V1\EventController@active');
    $router->post('version/config', 'V1\VersionController@config');
    $router->post('version/a', 'V1\VersionController@config');

    $router->post('feedback/submit', 'V1\FeedbackController@submit');

    $router->post('weather/now', 'V1\Weather\WeatherController@nowWeather');
    $router->post('weather/day', 'V1\Weather\WeatherController@dayWeather');
    $router->post('weather/alarm', 'V1\Weather\WeatherController@weatherAlarm');
    $router->post('weather/hour', 'V1\Weather\WeatherController@weatherHour');
    $router->post('weather/suggestion', 'V1\Weather\WeatherController@suggestion');
    $router->post('weather/location/search', 'V1\Weather\WeatherController@locationSearch');
    $router->post('weather/air/now', 'V1\Weather\WeatherController@airNow');
    $router->post('weather/air/day', 'V1\Weather\WeatherController@airDay');
    $router->post('weather/air/hour', 'V1\Weather\WeatherController@airHour');
    $router->post('weather/calendar', 'V1\Weather\WeatherController@calendar');

    //paid 付费服务
    $router->post('paid/app/goods', 'V1\Paid\GoodsController@getGoods');//获取支付渠道 商品
    $router->post('paid/app/wechat', 'V1\Paid\PayController@getWechatPrepayId');//微信支付
    $router->post('paid/vip/info', 'V1\Paid\VIPController@getVIPInfo');//查询是否是vip

    // 穿山甲CPS
    $router->post('douyin/link', 'V1\DouyinController@link');
});

