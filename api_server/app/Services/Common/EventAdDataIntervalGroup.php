<?php

declare(strict_types=1);
/**
 * 广告打点数据分数段分组统计
 * Author: wangshenyuan
 * Date: 2022/3/4
 */

namespace App\Services\Common;

use App\Services\Helper\Tools\LogPush;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class EventAdDataIntervalGroup
{

    const INTERVAL_TIME = 60 * 10;//统计间隔时长(秒)

    const PAGE_SIZE = 1000;//一次处理这么多数据

    const SEPARATOR = '&';//代码位信息分割符

    public $appcode;

    public $event_type;

    public $start_time;//间隔开始时间

    private static $instance = null;

    /**
     * @var \Redis
     */
    private $Redis;

    private function __construct()
    {
        $this->Redis = Redis::connection('event_ad');
    }

    /**
     * @return EventAdDataIntervalGroup|null
     */
    public static function getInstance(): ?EventAdDataIntervalGroup
    {
        if (self::$instance == null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * 设置appcode
     * @param string $appcode
     * @return EventAdDataIntervalGroup
     */
    public function setAppCode(string $appcode): EventAdDataIntervalGroup
    {
        $this->appcode = $appcode;
        return $this;
    }

    /**
     * 设置事件类型
     * @param string $type
     * @return $this
     */
    public function setEventType(string $type): EventAdDataIntervalGroup
    {
        $this->event_type = $type;
        return $this;
    }

    /**
     * 设置间隔开始结束时间
     * @param int $created_at
     * @return $this
     */
    public function setInterval(int $created_at): EventAdDataIntervalGroup
    {
        $start_time = $created_at - ($created_at % self::INTERVAL_TIME);
        $this->start_time = date('YmdHis', $start_time);
        return $this;
    }

    /**
     * 获取缓存根KEY
     * @return string
     */
    private function getRootKey(): string
    {
        return App::eventIntervalGroupKey($this->appcode, $this->event_type, $this->start_time);
    }

    /**
     * 获取间隔时间KEY
     * @return string
     */
    private function getIntervalKey(): string
    {
        return sprintf('%s:interval', $this->getRootKey());
    }

    /**
     * 获取设备集合KEY
     * @return string
     */
    private function getDeviceKey(): string
    {
        return sprintf('%s:device', $this->getRootKey());
    }

    /**
     * 获取设备广告信息集合KEY
     * @param string $device_id
     * @return string
     */
    private function getDeviceItemKey(string $device_id): string
    {
        return sprintf('%s:item:%s', $this->getRootKey(), $device_id);
    }

    /**
     * 添加设备ID到集合
     * @param string $device_id 设备ID
     * @param string $placement_id 代码位ID
     * @param float $ecpm 代码位客户端ecpm(元)
     * @param int $bidding 是否bidding [0]否[1]是
     */
    public function dataPush(string $device_id, string $placement_id, float $ecpm, int $bidding = 0)
    {
        if (!$device_id) {
            return;
        }

        $placement_info = $placement_id . self::SEPARATOR . $ecpm . self::SEPARATOR . $bidding;
        // 暂时关闭
//        $this->IntervalAdd();//设置间隔时长

        $device_key = $this->getDeviceKey();
//        $this->Redis->lrem($device_key, 0, $device_id);//移除列表中相同的元素

        $listDevice = $device_key . ':' . $device_id;
        if ($this->Redis->setnx($listDevice, 1)) {
            $this->Redis->expire($listDevice, 1200);
            $this->Redis->rpush($device_key, $device_id);
        }

        $item_key = $this->getDeviceItemKey($device_id);

        if ($this->Redis->zincrby($item_key, 1, $placement_info) == 1) {
            $this->Redis->expire($item_key, 48 * 3600);
        }
    }

    /**
     * 设置当前开始时间的间隔时长
     * @return void
     */
    private function IntervalAdd(): void
    {
        $interval_key = $this->getIntervalKey();
        $this->Redis->set($interval_key, self::INTERVAL_TIME);
        $this->Redis->expire($interval_key, 3600 * 3);
    }

    /**
     * @param string $key
     * @param int $num
     * @return array|mixed
     */
    private function batchGetList(string $key, int $num = 0)
    {
        if ($num <= 0) {
            $num = $this->Redis->llen($key);
        }
        $redis = $this->Redis->multi();
        $redis->lrange($key, 0, $num - 1);
        $redis->ltrim($key, $num, -1);
        $data = $redis->exec();
        return $data[0] ?? [];
    }

    /**
     * 数据入库
     */
    public function dataInstr()
    {
        $device_key = $this->getDeviceKey();

        $total_count = $this->Redis->llen($device_key);
        if (!$total_count) {
            return;
        }

        $interval_key = $this->getIntervalKey();
        $interval = $this->Redis->get($interval_key) ?? self::INTERVAL_TIME;

        while (true) {
            $device_data = $this->batchGetList($device_key, self::PAGE_SIZE);
            if (!count($device_data)) {
                break;
            }
            try {
                switch (true) {
                    case $this->event_type == 'ad_show':
                        $this->DoDataInstrShow($device_key, $device_data, (int)$interval);
                        break;
                }

            } catch (\Exception $e) {
                LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
            }
        }


    }

    /**
     * 处理展示数据入库
     * @param string $device_key
     * @param array $device_data 设备数据
     * @param int $interval 间隔时间
     * @throws \Exception
     */
    public function DoDataInstrShow(string $device_key, array $device_data, int $interval)
    {
        $start_time = strtotime($this->start_time);
        $end_time = $start_time + $interval;
        $start_end = date('Y-m-d H:i:s', $start_time) . '~' . date('Y-m-d H:i:s', $end_time);
        $fields = [
            'event_type',
            'start_end',
            'start_time',
            'end_time',
            'device_id',
            'max_level',
            'amount',
            'info',
            'create_time'
        ];

        $table = App::tableIntervalGroup($this->appcode);
        $keys = implode(',', $fields);
        $sql = "INSERT INTO {$table} ( {$keys} ) VALUES ";

        $sqlValue = false;

        $item_keys = [];
        foreach ($device_data as $device_id) {
            $value = [];
            $info = [];
            $amount = 0;
            $value['event_type'] = $this->event_type;
            $value['start_end'] = $start_end;
            $value['start_time'] = $start_time;
            $value['end_time'] = $end_time;
            $value['device_id'] = $device_id;
            $value['max_level'] = null;
            $value['amount'] = 0;
            $value['info'] = '';
            $value['create_time'] = time();

            $item_key = $this->getDeviceItemKey($device_id);
            $item_list = $this->Redis->zrange($item_key, 0, -1, true);//打点代码位信息列表
            foreach ($item_list as $placement_info => $times) {
                $placement_info = explode(self::SEPARATOR, (string)$placement_info);
                $placement_id = $placement_info[0];//代码位ID
                $ecpm = $placement_info[1] ?? 0;//代码位cepm
                $bidding = $placement_info[2] ?? 0;
                $placement_info = Account::getPlacementInfo($placement_id, (float)$ecpm);
                // bidding取上报值
                $ecpm = $bidding ? $ecpm : ($placement_info['ecpm'] ?? 0);
                $price = $ecpm / 1000;
                $level = $placement_info['level'] ?? 404;
                $info_item = [];
                $info_item['placement_id'] = $placement_id;
                $info_item['times'] = $times;
                $info_item['level'] = $level;
                $info_item['price'] = $price;

                $amount += $price * $times;
                $info[] = $info_item;

                if (!isset($value['max_level'])) {
                    $value['max_level'] = $level;
                } else {
                    $value['max_level'] = $value['max_level'] < $level ? $value['max_level'] : $level;
                }
            }

            if ($amount <= 0) {
                continue;
            }

            $value['amount'] = round($amount, 4);
            $value['info'] = json_encode($info);

            $item_keys[] = $item_key;
            $value = implode("','", $value);
            $sql .= "('{$value}'),";

            $sqlValue = true;
        }

        if ($sqlValue) {
            $sql = substr($sql, 0, -1);
            try {
                DB::insert($sql);
            } catch (\Exception $e) {
                App::batchAddList($device_key, $device_data);//执行失败再把数据放回队列
                LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
                throw $e;
            }
        }

        if ($item_keys) {
            /*批量删除设备数据缓存*/
            call_user_func_array([$this->Redis, 'del'], $item_keys);
        }
    }
}
