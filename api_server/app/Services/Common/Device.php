<?php

declare(strict_types=1);

namespace App\Services\Common;

use App\Lib\Amap\Amapi;
use App\Lib\IpSearch\IpSearch;
use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;
use Illuminate\Support\Facades\Redis;

class Device
{
    /**
     * @var array
     */
    public static $fields = [
        'imei',
        'oaid',
        'android_id',
        'ip'
    ];

    private static function getRedis()
    {
        /** @var \redis * */
        return Redis::connection('device');
    }

    /**
     * 初始化黑名单
     */
    public static function initBlack()
    {
        $list = DB::table('com_blacklist')
            ->select(self::$fields)
            ->get()
            ->toArray();

        foreach (self::$fields as $field) {
            $values = array_filter(array_unique(array_column($list, $field)));
            if (!empty($values)) {
                self::addBlack($field, $values);
            }
        }
    }

    /**
     * 是否在黑名单
     * @param string $field
     * @param string $value
     * @return bool
     */
    public static function isBlack(string $field, string $value)
    {
        return self::getRedis()->exists(self::getBlackKey($field, $value)) ? true : false;
    }

    /**
     * 添加至黑名单集合
     * @param array $deviceInfo
     */
    public static function addBlackDevice(array $deviceInfo)
    {
        if (!empty($deviceInfo['android_id']) && self::isBlack('android_id', $deviceInfo['android_id'])) {
            return;
        }

        foreach (self::$fields as $field) {
            if (!empty($deviceInfo[$field])) {
                self::addBlack($field, [$deviceInfo[$field]]);
            }
        }

        $deviceInfo['create_time'] = time();
        try {
            DB::table('com_blacklist')
                ->insertOrIgnore($deviceInfo);
        } catch (Exception $e) {
            LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * IP定位
     * @param string $ip
     * @return array
     */
    public static function location(string $ip)
    {
        try {
            $datFilePath = resource_path('dats/qqzeng-ip-china-utf8.dat');
            $reader = new IpSearch($datFilePath);
            $area_info = $reader->get($ip);
            list($continent, $country, $province, $city, $district, $isp, $areacode, $en, $cc, $lng, $lat) = explode("|", $area_info);
            $location = [
                'province' => $province,
                'city' => $city,
                'city_code' => $areacode,
                'way' => 'dat',
                'sync' => 1
            ];
            if (!$city) {
                throw new \Exception("not found city by ip");
            }
        } catch (\Exception $exception) {
            $location = (new Amapi())->ip($ip);
        }

        return $location ?? [];
    }

    /**
     * 添加黑名单
     * @param string $field
     * @param array $values
     */
    private static function addBlack(string $field, array $values)
    {
        $redis = self::getRedis();
        foreach ($values as $value) {
            $redis->setnx(self::getBlackKey($field, $value), 1);
        }
    }

    private static function getBlackKey($field, $value)
    {
        return sprintf('%s:black:%s:%s', env('APP_NAME'), $field, $value);
    }

}