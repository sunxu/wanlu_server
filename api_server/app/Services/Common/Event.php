<?php

declare(strict_types=1);

namespace App\Services\Common;

use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;
use Illuminate\Support\Facades\Redis;

class Event
{
    protected $maxNum = 10000;

    public $intervalTime = 60 * 10;

    public function active(string $appCode, string $type = 'active')
    {
        $insert = function (string $table, array $data) {
            if (empty($data)) {
                return;
            }
            $values = json_decode(reset($data), true);
            $keys = implode(',', array_keys($values));
            $sql = "INSERT INTO {$table} ( {$keys} ) VALUES ";
            foreach ($data as $v) {
                $value = implode("','", json_decode($v, true));
                $sql .= "( '{$value}' ),";
            }
            $sql = substr($sql, 0, -1);
            $sql .= " ON DUPLICATE KEY UPDATE 
                        time = VALUES(time), 
                        update_time = VALUES(create_time)";
            try {
                DB::insert($sql);
            } catch (Exception $e) {
                LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
            }
        };


        $table = App::tableEventActive($appCode);
        $listKey = App::eventListKey($type, $appCode);

        while (true) {
            $data = App::batchGetList($listKey, $this->maxNum);
            $insert($table, $data);
            if (count($data) < $this->maxNum) {
                return;
            }
        }
    }

    public function dialog(string $appCode, string $type = 'dialog')
    {
        $insert = function (string $table, array $data) {
            if (empty($data)) {
                return;
            }
            $insert = [];
            foreach ($data as $v) {
                $v = json_decode($v, true);
                if (!empty($v['uuid']) && !empty($v['key_type']) && !empty($v['req_type'])) {
                    $insert[$v['uuid'] . $v['key_type'] . $v['req_type']] = $v;
                }
            }
            try {
                DB::table($table)->insertOrIgnore($insert);
            } catch (Exception $e) {
                LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
            }
        };

        $table = App::tableEventDialog($appCode);
        $listKey = App::eventListKey($type, $appCode);

        while (true) {
            $data = App::batchGetList($listKey, $this->maxNum);
            $insert($table, $data);
            if (count($data) < $this->maxNum) {
                return;
            }
        }
    }

    public function strategy(string $appCode, string $type = 'strategy')
    {
        $insert = function (string $table, array $data) {
            if (empty($data)) {
                return;
            }
            $insert = [];
            foreach ($data as $v) {
                $insert[] = json_decode($v, true);
            }
            try {
                DB::table($table)->insertOrIgnore($insert);
            } catch (Exception $e) {
                LogPush::error(__CLASS__ . ':' . __FUNCTION__, [
                    'data' => $insert,
                    'exception' => $e->getMessage()
                ]);
            }
        };

        $table = App::tableLogStrategy($appCode);
        $listKey = App::logListKey($type, $appCode);

        while (true) {
            $data = App::batchGetList($listKey, $this->maxNum);
            $insert($table, $data);
            if (count($data) < $this->maxNum) {
                return;
            }
        }
    }

    public function ad(string $appCode, string $type)
    {
        $insert = function (string $table, array $data) {
            if (empty($data)) {
                return;
            }
            $values = json_decode(reset($data), true);
            $keys = implode(',', array_keys($values));
            $sql = "INSERT INTO {$table} ( {$keys} ) VALUES ";

            foreach ($data as $v) {
                $v = json_decode($v, true);
                $value = implode("','", $v);
                $sql .= "('{$value}'),";
            }
            $sql = substr($sql, 0, -1);
            $sql .= " ON DUPLICATE KEY UPDATE
                    times = times + 1,
                    update_time = VALUES(create_time)";

            try {
                DB::insert($sql);
            } catch (\Exception $e) {
                LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
            }
        };


        $table = App::tableEventAdType($appCode, $type);
        $listKey = App::eventListKey($type, $appCode);

        while (true) {
            $data = App::batchGetList($listKey, $this->maxNum);
            $insert($table, $data);
            if (count($data) < $this->maxNum) {
                break;
            }
        }
    }

    public function revenue(string $appCode, array $data)
    {
        $redis = $this->getRedis();
        $deviceId = $data['android_id'] ?? '';
        $ecpm = json_decode($data['info'] ?? '[]', true)['realEcpm'] ?? 0;
        $key = $this->getRevenueKey($appCode, $data['create_time']);
        if ($deviceId && $ecpm && $redis->zincrby($key, $ecpm, $deviceId) == $ecpm) {
            $redis->expire($key, 48 * 3600);
        }
    }

    public function insertRevenue(string $appCode, int $timestamp)
    {
        $key = $key = $this->getRevenueKey($appCode, $timestamp);
        $redis = $this->getRedis();
        $limit = $this->maxNum;
        $time = $this->getTime($timestamp);
        $tableEventAdRevenue = App::tableEventAdRevenue($appCode);
        while ($list = $redis->zRange($key, 0, $limit - 1, true)) {
            $insert = [];
            $createTime = time();
            foreach ($list as $deviceId => $amount) {
                $insert[] = [
                    'time' => $time,
                    'device_id' => $deviceId,
                    'amount' => round($amount / 1000, 5),
                    'create_time' => $createTime,
                ];
            }
            DB::table($tableEventAdRevenue)->insertOrIgnore($insert);
            $members = array_keys($list);
            array_unshift($members, $key);
            call_user_func_array([$redis, 'zRem'], $members);
        }
    }

    protected function getRevenueKey(string $appCode, int $timestamp)
    {
        return sprintf('%s:revenue:%s:%s', env('APP_NAME'), $appCode, $this->getTime($timestamp));
    }

    protected function getTime(int $timestamp)
    {
        return $time = date('YmdHis', $timestamp - $timestamp % $this->intervalTime);
    }


    protected function getRedis()
    {
        /** @var \redis * */
        return Redis::connection('event');
    }
}