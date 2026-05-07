<?php

declare(strict_types=1);
/**
 * 广告打点数据批量入库
 * Author: wangshenyuan
 * Date: 2022/3/3
 */

namespace App\Services\Common;

use App\Jobs\KeepJob;
use App\Services\Helper\Tools\Dding;
use App\Services\Helper\Tools\LogPush;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class EventAdDataBatchInsert
{

    const EVENT_TYPE = ['ad_show'];//使用集合过滤的事件


    /**
     * 类型
     * @var array
     */
    public $key = ['ad_show'];

    /**
     * 单次取队列最大数量
     * @var int
     */
    protected $maxNum = 5000;

    protected $appcode = [];

    /**
     * uuid缓存过期时间
     * @var int
     */
    protected $uuidExp = 3600 * 12;

    public function __construct()
    {
        // 工具-自投
        $apps = App::codeList([data_get(App::$appType, 'tool')], [], [data_get(App::$adStatus, 'self')]);
        $this->appcode = array_keys($apps);
    }

    /**
     * 获取需要处理的Appcode
     * @return array
     */
    public function getAppcode(): array
    {
        return $this->appcode;
    }


    /**
     * 广告打点
     * @param string $appcode
     */
    public function insert(string $appcode)
    {
        if (empty($appcode)) {
            return;
        }
        foreach ($this->key as $key) {
            $table = App::tableEventAdType($appcode, $key);
//            App::tableEventAdCreate($table);
            $listKey = App::eventListKey($key, $appcode);

            while (true) {
                $data = App::batchGetList($listKey, $this->maxNum);

                if (in_array($appcode, $this->appcode) && in_array($key, self::EVENT_TYPE)) {
                    $this->doInsertByRedis($appcode, $table, $data, $key, $listKey);
                } else {
                    $this->doInsert($table, $data, $listKey);
                }
//                $this->doKeep($data, $appcode, $key);

//                if (App::isProduct()) {
//                    $this->doInsertSls($appcode, $key, $data);
//                }

                if (count($data) < $this->maxNum) {
                    break;
                }
            }
        }
    }

    /**
     * 数据放入阿里云SLS
     * @param $appcode
     * @param $ad_event
     * @param $data
     * @return void
     */
    public function doInsertSls($appcode, $ad_event, $data)
    {
        if (count($data)) {
            try {
                $contents = [];
                foreach ($data as $v) {
                    $v = json_decode($v, true);
                    $v['log_time'] = $v['create_time'];
                    $v['ad_event'] = $ad_event;
                    $contents[] = $v;
                }

                $Sls = new AliyunSls();
                $Sls->SetLogstore('ad-event');
                $Sls->SetTopic($appcode);
                $Sls->PutLogs($contents);
            } catch (\Exception $exception) {
                LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $exception->getMessage()]);
            }
        }
    }

    /**
     * 留存
     *
     * @param array $data
     * @param string $appcode
     * @return void
     */
    public function doKeep(array $data, string $appcode, string $key): void
    {
        if ($key !== 'ad_show' || !in_array($appcode, $this->appcode)) {
            return;
        }
        $data = collect($data)->keyBy('android_id')->toArray();
        dispatch((new KeepJob(['data' => $data, 'appCode' => $appcode])));
    }

    /**
     * 执行入库
     * @param $table
     * @param $data
     * @param $listKey
     * @param $sourceSetKey
     * @param $uuids
     */
    public function doInsert($table, $data, $listKey, $sourceSetKey = '', $uuids = [])
    {
        if (empty($data)) {
            return;
        }
        $values = json_decode(reset($data), true);
        $keys = implode(',', array_keys($values));
        $sql = "INSERT INTO {$table} ( {$keys} ) VALUES ";
        foreach ($data as $v) {
            $value = implode("','", json_decode($v, true));
            $sql .= "('{$value}'),";
        }
        $sql = substr($sql, 0, -1);
//        $sql .= " ON DUPLICATE KEY UPDATE
//                    page = VALUES(page),
//                    info = VALUES(info),
//                    ad_time = VALUES(ad_time),
//                    times = times + 1,
//                    update_time = VALUES(create_time)";
        $sql .= " ON DUPLICATE KEY UPDATE
                    times = times + 1,
                    update_time = VALUES(create_time)";

        try {
            DB::insert($sql);
        } catch (\Exception $e) {
//            App::batchAddList($listKey, $data);//执行失败再把数据放回队列
            if (!empty($sourceSetKey)) {
                App::eventSourceSetBatchRemove($sourceSetKey, $uuids);//移除已添加集合的uuid
            }
            LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
        }
    }

    /**
     * 通过Redis过滤重复数据
     * @param $appcode
     * @param $table
     * @param $data
     * @param $event_type
     * @param $listKey
     */
    public function doInsertByRedis($appcode, $table, $data, $event_type, $listKey)
    {
        if (empty($data)) {
            return;
        }

        $sourceSetKey = App::eventSourceSetKey($event_type, $appcode);
        $values = json_decode(reset($data), true);
        $keys = implode(',', array_keys($values));
        $sql = "INSERT INTO {$table} ( {$keys} ) VALUES ";
        $uuids = [];
        foreach ($data as $v) {
            $v = json_decode($v, true);
            $uuid = $v['uuid'];
            $value = implode("','", $v);

            $info = isset($v['info']) ? json_decode($v['info'], true) : [];
            $create_time = $v['create_time'] ?? time();
            $device_id = $v['android_id'] ?? '';
            $placement_id = $info['networkPlacementId'] ?? '';
            $bidding = (int)($info['biddingType'] ?? 0);
            $ecpm = isset($info['ecpm']) ? round((float)$info['ecpm'], 4) : 0;// ECPM【元】保留4位小数
            if ($event_type == 'ad_show') {
                /*广告打点分时段统计*/
                EventAdDataIntervalGroup::getInstance()->setAppCode($appcode)->setEventType($event_type)->setInterval($create_time)->dataPush($device_id, $placement_id, $ecpm, $bidding);
            }
            //添加uuid至redis有序集合，已存在则返回0，不存在返回1
            if (Redis::connection('event_ad')->setnx("{$sourceSetKey}:{$uuid}", 1)) {
                Redis::connection('event_ad')->expire("{$sourceSetKey}:{$uuid}", $this->uuidExp);
                $uuids[] = $uuid;
                $sql .= "('{$value}'),";

//                /*广告打点分时段统计*/ 关闭点击类型
//                if ($event_type == 'ad_click') {
//                    EventAdDataIntervalGroup::getInstance()->setAppCode($appcode)->setEventType($event_type)->setInterval($create_time)->dataPush($device_id, $placement_id, $ecpm);
//                }
            } else {
                DB::table($table)->where('uuid', $uuid)->increment('times');
            }
        }
        if (!count($uuids)) {
            return;
        }
        $sql = substr($sql, 0, -1);
        try {
            DB::insert($sql);
        } catch (\Exception $e) {
//            echo '【打点入库使用集合过滤重复uuid】插入失败，继续使用原方法插入。失败原因：' . substr($e->getMessage(), 0, 1000) . PHP_EOL;
            Dding::push('【打点入库使用集合过滤重复uuid】插入失败，继续使用原方法插入。失败原因：' . substr($e->getMessage(), 0, 1000));
            $this->doInsert($table, $data, $listKey, $sourceSetKey, $uuids);
        }

    }

    /**
     * 清除过期的uuid
     * @param string $appcode
     */
    public function clearExp(string $appcode)
    {
        foreach ($this->key as $key) {
            $sourceSetKey = App::eventSourceSetKey($key, $appcode);
            $list = App::eventSourceSetExp($sourceSetKey);
            App::eventSourceSetBatchRemove($sourceSetKey, $list);
        }
    }
}
