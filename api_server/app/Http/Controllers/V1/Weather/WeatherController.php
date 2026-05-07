<?php

namespace App\Http\Controllers\V1\Weather;

use App\Lib\Gaofen\Gaofen;
use App\Services\Helper\Helper;
use App\Http\Controllers\V1\AppBaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WeatherController  extends AppBaseController
{
    public function nowWeather(Request $request)
    {
        Log::channel('weather_log')->info('access', ['uri' => $request->getPathInfo(), 'params' => $request->all()]);
        $params = data_get($request->get('_param'), 'data');
        if(!($areacode = data_get($params, 'areacode'))){
            if(!($lonlat = data_get($params, 'lonlat'))){
                return Helper::response(1001);
            }
            try {
                $result = app(Gaofen::class)->requestGaofen(Gaofen::API_FUNCTION_POI, ['lonlat' => $lonlat], false);
                $areacode = data_get($result, 'location.areacode');
            }catch (\Exception $exception){
                return Helper::response(1000);
            }
        }
        try {
            $result = app(Gaofen::class)->requestGaofen(Gaofen::API_WEATHER_HOUR, ['areacode' => $areacode, 'hours' => 24]);
        }catch (\Exception $exception){
            return Helper::response(1000);
        }
        $hourly_fcsts = data_get($result, 'result.hourly_fcsts');
        $realtime = array_shift($hourly_fcsts);
        $realtime = [
            'text' => data_get($realtime, 'text'),
            'code' => data_get($realtime, 'code'),
            'temp' => data_get($realtime, 'temp_fc'),
            'wind_class' => data_get($realtime, 'wind_class'),
            'wind_dir' => data_get($realtime, 'wind_dir'),
            'feels_like' => data_get($realtime, 'feels_like'),
            'rh' => data_get($realtime, 'rh'),
            'prec' => data_get($realtime, 'prec'),
        ];
        data_set($result, 'result.realtime', $realtime);

        return Helper::response(0, data_get($result, 'result'));

    }

    public function dayWeather(Request $request)
    {
        Log::channel('weather_log')->info('access', ['uri' => $request->getPathInfo(), 'params' => $request->all()]);
        $params = data_get($request->get('_param'), 'data.list',[]);
        $isYesterday = data_get($request->get('_param'), 'data.is_yesterday', false);

        $response = [];
        foreach ($params as $city){
            $day = $isYesterday?data_get($city, 'day', 7) - 1:data_get($city, 'day', 7);

            if(!($areacode = data_get($city, 'areacode'))){
                if(!($lonlat = data_get($city, 'lonlat'))){
                    return Helper::response(1001);
                }
                try {
                    $result = app(Gaofen::class)->requestGaofen(Gaofen::API_FUNCTION_POI, ['lonlat' => $lonlat], false);
                    $areacode = data_get($result, 'location.areacode');
                }catch (\Exception $exception){
                    return Helper::response(1000);
                }
            }
            try {
                $weather = app(Gaofen::class)->requestGaofen(Gaofen::API_WEATHER_DAY, ['areacode' => $areacode, 'days' => $day]);
                $weather = data_get($weather, 'result');
            }catch (\Exception $exception){
                return Helper::response(1000);
            }
            //当天天气
            try {
                $weatherNow = app(Gaofen::class)->requestGaofen(Gaofen::API_WEATHER_HOUR, ['areacode' => $areacode, 'hours' => 24]);
            }catch (\Exception $exception){
                return Helper::response(1000);
            }
            $hourly_fcsts = data_get($weatherNow, 'result.hourly_fcsts');
            $realtime = array_shift($hourly_fcsts);
            $realtime = [
                'text' => data_get($realtime, 'text'),
                'code' => data_get($realtime, 'code'),
                'temp' => data_get($realtime, 'temp_fc'),
                'wind_class' => data_get($realtime, 'wind_class'),
                'wind_dir' => data_get($realtime, 'wind_dir'),
                'feels_like' => data_get($realtime, 'feels_like'),
                'rh' => data_get($realtime, 'rh'),
                'prec' => data_get($realtime, 'prec'),
            ];
            data_set($weather, 'realtime', $realtime);
            //昨日天气
            if($isYesterday){
                try {
                    $weatherYesterday = app(Gaofen::class)->requestGaofen(Gaofen::API_WEATHER_YESTERDAY, ['areacode' => $areacode, 'days' => 1]);
                }catch (\Exception $exception){
                    return Helper::response(1000);
                }
                $weatherYesterday = data_get($weatherYesterday, 'result.daily_fcsts.0');
                $daily_fcsts = data_get($weather, 'daily_fcsts');
                array_unshift($daily_fcsts,$weatherYesterday);
                data_set($weather, 'daily_fcsts', $daily_fcsts);
            }

            $response[] = $weather;
        }
        //空气质量
        foreach ($response as $key => $value){
            $areacode = data_get($value, 'location.areacode');
            $daily_fcsts = data_get($value, 'daily_fcsts');
            $day = $isYesterday?count($daily_fcsts) - 1: count($daily_fcsts);
            try {
                $airDay = app(Gaofen::class)->requestGaofen(Gaofen::API_AIR_DAY, ['areacode' => $areacode, 'days' => $day]);
            }catch (\Exception $exception){
                return Helper::response(1000);
            }
            $aqi_daily_fcsts = collect(data_get($airDay, 'result.aqi_daily_fcsts'))->keyBy('date')->all();
            $daily_fcsts = collect($daily_fcsts)->map(function ($item) use ($aqi_daily_fcsts){
                $date = data_get($item, 'date');
                $aqi = [
                    'aqi' => data_get($aqi_daily_fcsts, "{$date}.aqi"),
                    'aqi_level' => data_get($aqi_daily_fcsts, "{$date}.aqi_level"),
                ];
                data_set($item, 'aqi', $aqi);
                return $item;
            })->all();
            data_set($value, 'daily_fcsts', $daily_fcsts);
            $response[$key] = $value;
        }
        return Helper::response(0, ['records' => $response]);
    }

    public function suggestion(Request $request)
    {
        Log::channel('weather_log')->info('access', ['uri' => $request->getPathInfo(), 'params' => $request->all()]);
        $params = data_get($request->get('_param'), 'data');
        if(!($areacode = data_get($params, 'areacode'))){
            if(!($lonlat = data_get($params, 'lonlat'))){
                return Helper::response(1001);
            }
            try {
                $result = app(Gaofen::class)->requestGaofen(Gaofen::API_FUNCTION_POI, ['lonlat' => $lonlat], false);
                $areacode = data_get($result, 'location.areacode');
            }catch (\Exception $exception){
                return Helper::response(1000);
            }
        }

        try {
            $result = app(Gaofen::class)->requestGaofen(Gaofen::API_WEATHER_SUGGESTION, ['areacode' => $areacode, 'days' => data_get($params, 'day', 1)]);
        }catch (\Exception $exception){
            return Helper::response(1000);
        }

        return Helper::response(0, data_get($result, 'result'));
    }

    public function airNow(Request $request)
    {
        Log::channel('weather_log')->info('access', ['uri' => $request->getPathInfo(), 'params' => $request->all()]);
        $params = data_get($request->get('_param'), 'data');
        if(!($areacode = data_get($params, 'areacode'))){
            if(!($lonlat = data_get($params, 'lonlat'))){
                return Helper::response(1001);
            }
            try {
                $result = app(Gaofen::class)->requestGaofen(Gaofen::API_FUNCTION_POI, ['lonlat' => $lonlat], false);
                $areacode = data_get($result, 'location.areacode');
            }catch (\Exception $exception){
                return Helper::response(1000);
            }
        }

        try {
            $result = app(Gaofen::class)->requestGaofen(Gaofen::API_AIR_NOW, ['areacode' => $areacode]);
        }catch (\Exception $exception){
            return Helper::response(1000);
        }
        return Helper::response(0, data_get($result, 'result'));
    }

    public function airDay(Request $request)
    {
        Log::channel('weather_log')->info('access', ['uri' => $request->getPathInfo(), 'params' => $request->all()]);
        $params = data_get($request->get('_param'), 'data');
        if(!($areacode = data_get($params, 'areacode'))){
            if(!($lonlat = data_get($params, 'lonlat'))){
                return Helper::response(1001);
            }
            try {
                $result = app(Gaofen::class)->requestGaofen(Gaofen::API_FUNCTION_POI, ['lonlat' => $lonlat], false);
                $areacode = data_get($result, 'location.areacode');
            }catch (\Exception $exception){
                return Helper::response(1000);
            }
        }

        try {
            $result = app(Gaofen::class)->requestGaofen(Gaofen::API_AIR_DAY, ['areacode' => $areacode, 'days' => data_get($params, 'day', 7)]);
        }catch (\Exception $exception){
            return Helper::response(1000);
        }

        return Helper::response(0, data_get($result, 'result'));
    }

    public function weatherHour(Request $request)
    {
        Log::channel('weather_log')->info('access', ['uri' => $request->getPathInfo(), 'params' => $request->all()]);
        $params = data_get($request->get('_param'), 'data');
        if(!($areacode = data_get($params, 'areacode'))){
            if(!($lonlat = data_get($params, 'lonlat'))){
                return Helper::response(1001);
            }
            try {
                $result = app(Gaofen::class)->requestGaofen(Gaofen::API_FUNCTION_POI, ['lonlat' => $lonlat], false);
                $areacode = data_get($result, 'location.areacode');
            }catch (\Exception $exception){
                return Helper::response(1000);
            }
        }

        try {
            $result = app(Gaofen::class)->requestGaofen(Gaofen::API_WEATHER_HOUR, ['areacode' => $areacode, 'hours' => data_get($params, 'hour', 24)]);
        }catch (\Exception $exception){
            return Helper::response(1000);
        }
        return Helper::response(0, data_get($result, 'result'));
    }

    public function airHour(Request $request)
    {
        Log::channel('weather_log')->info('access', ['uri' => $request->getPathInfo(), 'params' => $request->all()]);
        $params = data_get($request->get('_param'), 'data');
        if(!($areacode = data_get($params, 'areacode'))){
            if(!($lonlat = data_get($params, 'lonlat'))){
                return Helper::response(1001);
            }
            try {
                $result = app(Gaofen::class)->requestGaofen(Gaofen::API_FUNCTION_POI, ['lonlat' => $lonlat], false);
                $areacode = data_get($result, 'location.areacode');
            }catch (\Exception $exception){
                return Helper::response(1000);
            }
        }

        try {
            $result = app(Gaofen::class)->requestGaofen(Gaofen::API_AIR_HOUR, ['areacode' => $areacode, 'hours' => data_get($params, 'hour', 24)]);
        }catch (\Exception $exception){
            return Helper::response(1000);
        }
        return Helper::response(0, data_get($result, 'result'));
    }

    public function calendar(Request $request)
    {
        Log::channel('weather_log')->info('access', ['uri' => $request->getPathInfo(), 'params' => $request->all()]);
        $params = data_get($request->get('_param'), 'data');

        try {
            $result = app(Gaofen::class)->requestGaofen(Gaofen::API_CALENDAR, ['days' => data_get($params, 'day', 7) + 1]);
        }catch (\Exception $exception){
            return Helper::response(1000);
        }
        $result = data_get($result, 'result');
        array_shift($result);
        return Helper::response(0, ['records' => $result]);
    }

    public function locationSearch(Request $request)
    {
        Log::channel('weather_log')->info('access', ['uri' => $request->getPathInfo(), 'params' => $request->all()]);
        $params = data_get($request->get('_param'), 'data');
        if(!($location = data_get($params, 'location'))){
            return Helper::response(1001);
        }

        try {
            $result = app(Gaofen::class)->requestGaofen(Gaofen::API_FUNCTION_CITY, [
                'location' => $location, 'items' => data_get($params, 'items', 20),
                'area' => data_get($params, 'area', 'china'),
                'language' => data_get($params, 'language', 'CHN'),
                'withTz' => data_get($params, 'withTz', false),
                'withPoi' => data_get($params, 'withPoi', false),
            ]);
        }catch (\Exception $exception){
            return Helper::response(1000);
        }

        return Helper::response(0, ['citys' => data_get($result, 'areaList')]);
    }

    public function weatherAlarm(Request $request)
    {
        Log::channel('weather_log')->info('access', ['uri' => $request->getPathInfo(), 'params' => $request->all()]);
        try {
            $result = app(Gaofen::class)->requestGaofen(Gaofen::API_WEATHER_ALARM);
        }catch (\Exception $exception){
            return Helper::response(1000);
        }
        $result = data_get($result, 'result');
        $result = array_slice($result, 0, 50);
        $redis = Redis::connection()->client();
        $redisKey ='tools:weather:alarmguide';
        if(!$redis->exists($redisKey)){
            $guides = DB::table('weather_alarm_guide')->get()->all();
            foreach ($guides as $value){
                $filedKey = strtolower(data_get($value, 'type_code') .'-'. data_get($value, 'level_code'));
                $redis->hSet($redisKey, $filedKey, json_encode($value));
            }
        }
        $endpoint = config('aliyun.oss_public_endpoint');
        foreach ($result as $key => $value){
            $filedKey = strtolower(data_get($value, 'typeCode') .'-'. data_get($value, 'levelCode'));
            $guide = $redis->hget($redisKey, $filedKey);
            $guide = json_decode($guide, true);
            $result[$key]['icon_path'] = $endpoint . data_get($guide, 'icon_path');
            $result[$key]['guide'] = data_get($guide, 'guide');
        }
        return Helper::response(0, ['records' => $result]);
    }
}