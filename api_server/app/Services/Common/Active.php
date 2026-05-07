<?php

namespace App\Services\Common;

use App\Jobs\DeviceActiveJob;
use App\Jobs\DeviceKeepJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class Active
{
    /**
     * 设备活跃
     * @param string $appCode
     * @param string $activeDate
     * @param string $deviceId
     * @param int $installTime
     * @param int $activateTime
     * @param int $adTime
     */
    public static function device(string $appCode, string $activeDate, string $deviceId, int $installTime, int $activateTime, int $adTime = 0)
    {
        $activeDate = date('Ymd', strtotime($activeDate));
        $key = self::getActiveKey($appCode, $activeDate, $deviceId);

        if (self::getRedis()->setnx($key, $adTime ? 1 : 0)) {
            self::getRedis()->expireAt($key, strtotime($activeDate) + 86400 + 7200);
            $installDate = date('Ymd', $installTime);
            $installKeepNum = self::getKeepDays($installDate, $activeDate);
            $activateDate = $activateTime ? date('Ymd', $activateTime) : 0;
            $activateKeepNum = $activateDate ? self::getKeepDays($activateDate, $activeDate) : 0;

            DB::table(App::tableActiveKeep($appCode))->updateOrInsert([
                'device_id' => $deviceId,
                'active_day' => $activeDate,
            ], [
                'device_id' => $deviceId,
                'active_day' => $activeDate,
                'install_day' => $installDate,
                'install_keep_num' => $installKeepNum,
                'activate_day' => $activateDate,
                'activate_keep_num' => $activateKeepNum,
                'is_ad_report' => $adTime ? 1 : 0,
                'created_time' => time()
            ]);

            if ($adTime) {
                self::keep($appCode, $deviceId, $adTime);
            }
        } elseif ($adTime && self::getRedis()->get($key) === '0' && self::getRedis()->incr($key)) {
            DB::table(App::tableActiveKeep($appCode))
                ->where('device_id', $deviceId)
                ->where('active_day', $activeDate)
                ->update([
                    'is_ad_report' => 1,
                ]);
            self::keep($appCode, $deviceId, $adTime);
        }
    }

    /**
     * 激活留存-24小时
     * @param string $appCode
     * @param string $deviceId
     * @param int $activateTime
     * @param int $adTime
     */
    public static function activateKeep(string $appCode, string $deviceId, int $activateTime, int $adTime = 0)
    {
        if ($activateTime == 0 || $adTime == 0) {
            return;
        }

        $keepTimestamp = $adTime - $activateTime;
        // 24-48小时
        if ($keepTimestamp < 86400 || $keepTimestamp > 172800) {
            return;
        }

        $key = sprintf('%s:keep:%s:%s:%s', env('APP_NAME'), $appCode, date('Ymd', $activateTime), $deviceId);
        $redis = Redis::connection('keep');
        if ($redis->setnx($key, ceil($keepTimestamp / 3600))) {
            $redis->expireAt($key, $adTime + 86400 + 7200);
            self::keep($appCode, $deviceId, $adTime);
        }
    }

    /**
     * 被扣补量
     * @param string $appCode
     * @param string $deviceId
     * @param string $accountId
     * @param int $activateTime
     * @param int $attributeTime
     * @param int $adTime
     */
    public static function activateFill(string $appCode, string $deviceId, string $accountId, int $activateTime, int $attributeTime, int $adTime = 0)
    {
        if ($attributeTime == 0 || $activateTime > 0 || $adTime == 0) {
            return;
        }

        $redis = Redis::connection('keep');
        $key = self::getKeyNotifyFill($appCode, $deviceId);

        if (!$redis->exists($key)
            ||
            // 自然日留存
            (!Account::keep24($accountId)
                && date('Ymd', $attributeTime) != date('Ymd', $adTime - 86400)
            )
            ||
            // 24小时留存
            (Account::keep24($accountId)
                && (
                    $adTime - $attributeTime < 86400
                    || $adTime - $attributeTime > 172800
                )
            )
        ) {
            return;
        }

        $keepTimestamp = $adTime - $attributeTime;

        $key = sprintf('%s:attribute:%s:%s:%s', env('APP_NAME'), $appCode, date('Ymd', $attributeTime), $deviceId);

        if ($redis->setnx($key, ceil($keepTimestamp / 3600))) {
            $redis->expireAt($key, $adTime + 86400 + 7200);
            dispatch((new DeviceActiveJob([
                'app_code' => $appCode,
                'device_id' => $deviceId,
                'active_time' => $adTime,
                'notify' => true // 补量
            ]))->onQueue('{device_active}'));
        }
    }

    /**
     * 补量设置
     * @param string $appCode
     * @param string $deviceId
     */
    public static function setNotifyFill(string $appCode, string $deviceId)
    {
        $redis = Redis::connection('keep');
        $key = self::getKeyNotifyFill($appCode, $deviceId);
        if ($redis->setnx($key, 1)) {
            $redis->expire($key, 172800);
        }
    }

    protected static function getKeyNotifyFill(string $appCode, string $deviceId)
    {
        return sprintf('%s:notify-fill:%s:%s', env('APP_NAME'), $appCode, $deviceId);
    }

    /**
     * 广告留存
     * @param string $appCode
     * @param string $deviceId
     * @param int $keepTime
     */
    protected static function keep(string $appCode, string $deviceId, int $keepTime)
    {
        dispatch(new DeviceKeepJob([
            'app_code' => $appCode,
            'device_id' => $deviceId,
            'keep_time' => $keepTime,
        ]));
    }

    protected static function getActiveKey(string $appCode, string $activeDate, string $deviceId)
    {
        return sprintf('%s:active:%s:%s:%s', env('APP_NAME'), $appCode, $activeDate, $deviceId);
    }

    protected static function getRedis($name = 'active')
    {
        return Redis::connection($name);
    }

    /**
     * 计算留存天数
     * @param string $startDate
     * @param string $endDate
     * @return int
     */
    protected static function getKeepDays(string $startDate, string $endDate)
    {
        return (int)floor(abs((strtotime($endDate) - strtotime($startDate))) / 86400);
    }
}