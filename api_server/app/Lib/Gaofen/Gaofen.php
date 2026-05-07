<?php

namespace App\Lib\Gaofen;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class Gaofen
{
    protected const SECRET = 'IJeekwyoIm8BNv73A5xBDRSff88AqlTK';

    protected const CLIENT_CONFIG = [
        'base_uri' => 'http://gfapi.mlogcn.com'
    ];

    const API_WEATHER_NOW = 'weather_now';//智能天气实况
    const API_WEATHER_DAY = 'weather_day';//90天气实况
    const API_WEATHER_ALARM = 'weather_alarm';//天气预警
    const API_WEATHER_HOUR = 'weather_hour';//小时天气
    const API_WEATHER_SUGGESTION = 'weather_suggestion';//生活指数
    const API_FUNCTION_CITY = 'function_city';//城市搜索
    const API_FUNCTION_POI = 'function_poi';//城市搜索
    const API_AIR_NOW = 'air_now';//空气指数
    const API_AIR_DAY = 'air_day';//空气指数
    const API_AIR_HOUR = 'air_hour';//空气指数
    const API_CALENDAR = 'calendar';//万年历

    const API_WEATHER_YESTERDAY = 'weather_yesterday';//昨日天气
    const API_AIR_YESTERDAY = 'are_yesterday';//昨日空气


    protected const API_LIST = [
        self::API_WEATHER_NOW => ['uri_path' => '/weather/v001/now', 'method' => 'GET', 'ttl' => 8 * 60 * 60, 'log' => true],
        self::API_WEATHER_DAY => ['uri_path' => '/weather/v001/day', 'method' => 'GET', 'expire_type' => 'day', 'log' => true],
        self::API_WEATHER_ALARM => ['uri_path' => '/weather/v001/alarm', 'method' => 'GET', 'expire_type' => 'day', 'log' => true],
        self::API_WEATHER_SUGGESTION => ['uri_path' => '/lives_geo/v001/suggestion', 'method' => 'GET', 'expire_type' => 'day', 'log' => true],
        self::API_WEATHER_HOUR => ['uri_path' => '/weather/v001/hour', 'method' => 'GET', 'expire_type' => 'hour', 'log' => true],
        self::API_AIR_NOW => ['uri_path' => '/air/v001/aqi', 'method' => 'GET', 'ttl' => 8 * 60 * 60, 'log' => true],
        self::API_AIR_DAY => ['uri_path' => '/air/v001/airforecast', 'method' => 'GET', 'expire_type' => 'day', 'log' => true],
        self::API_AIR_HOUR => ['uri_path' => '/air/v001/airhourly', 'method' => 'GET', 'expire_type' => 'hour', 'log' => true],
        self::API_CALENDAR => ['uri_path' => '/lives_geo/v001/calendar', 'method' => 'GET', 'expire_type' => 'day', 'log' => true],
        self::API_WEATHER_YESTERDAY => ['uri_path' => '/weather/v001/yesterday', 'method' => 'GET', 'expire_type' => 'day', 'log' => true],
        self::API_AIR_YESTERDAY => ['uri_path' => '/air/v001/airranking', 'method' => 'GET', 'expire_type' => 'day', 'log' => true],

        self::API_FUNCTION_CITY => ['uri_path' => '/function/v001/city', 'method' => 'GET', 'ttl' => 24 * 60 * 60, 'log' => false],
        self::API_FUNCTION_POI => ['uri_path' => '/function/v001/poi', 'method' => 'GET', 'ttl' => 24 * 60 * 60, 'log' => false],

    ];

    public function requestGaofen(string $request_type, array $params = [], bool $is_cache = true)
    {
        $redis = Redis::connection()->client();
        $redisKey = "gaofen:{$request_type}:" . md5(json_encode($params));
        if($is_cache && $redis->exists($redisKey)){
            $result = $redis->get($redisKey);
            return json_decode($result, true);
        }
        data_set($params, 'key', self::SECRET);
        $client = new Client(self::CLIENT_CONFIG);
        $api = data_get(self::API_LIST, $request_type);
        $uri = data_get($api, 'uri_path') . '?'.http_build_query($params);
        try {
            $response = $client->request('GET', $uri);
        }catch (\Exception $exception){
            throw new \Exception('请求失败:'. $exception->getMessage(), $exception->getCode(), $exception);
        }
        $result = json_decode($response->getBody()->getContents(), true);
        Log::channel('weather_log')->info('request_gaofen', ['type' => $request_type, 'uri' => $uri, 'result' => $result]);
        if(data_get($result, 'status', 0) !== 0){
            throw new \Exception('请求失败:'. data_get($result, 'status'));
        }
        if($is_cache){
            $this->expireCache($request_type, $redisKey, $result);
        }
        return $result;
    }

    protected function expireCache(string $request_type, string $redis_key, array $result)
    {
        $redis = Redis::connection()->client();
        $api = data_get(self::API_LIST, $request_type);
        if($ttl = data_get($api, 'ttl')){
            $redis->set($redis_key, json_encode($result));
            $redis->expire($redis_key, $ttl);
            return;
        }
        $expire_type = data_get($api, 'expire_type');
        switch($expire_type){
            case 'day':
                $ttl = strtotime('tomorrow') - time();
                $redis->set($redis_key, json_encode($result));
                $redis->expire($redis_key, $ttl);
                break;
            case 'hour':
                $ttl = strtotime(date("Y-m-d H:00:00")) + 60 * 60 - time();

                $redis->set($redis_key, json_encode($result));
                $redis->expire($redis_key, $ttl);
                break;
        }
    }

    public static function getAqiLevel(int $aqi): string
    {
        switch ($aqi){
            case $aqi >= 0 && $aqi <= 50:
                $level = '优';
                break;
            case $aqi >= 51 && $aqi <= 100:
                $level = '良';
                break;
            case $aqi >= 101 && $aqi <= 150:
                $level = '轻度';
                break;
            case $aqi >= 151 && $aqi <= 200:
                $level = '中度';
                break;
            case $aqi >= 201 && $aqi <= 300:
                $level = '重度';
                break;
            case $aqi >300:
                $level = '严重';
                break;
            default:
                $level = '优';
        }
        return $level;
    }
}