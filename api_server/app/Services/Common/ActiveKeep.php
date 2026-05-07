<?php
declare(strict_types=1);
/**
 * 活跃留存分析处理
 * Author: jyk-wsy
 * Date: 2022/4/6
 */

namespace App\Services\Common;

use App\Constants\AdConst;
use App\Jobs\NotifyAdKeepJob;
use App\Services\Helper\Tools\LogPush;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ActiveKeep
{

    public const PAGE_SIZE = 2000;//每页处理设备数

    private $appcode;

    private static $instance = null;

    /**
     * @var \Redis
     */
    private $Redis;

    private function __construct()
    {
        $this->Redis = Redis::connection('event');
    }

    /**
     * @return ActiveKeep|null
     */
    public static function getInstance(): ?ActiveKeep
    {
        if (self::$instance == null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * @param $appcode
     * @return $this
     */
    public function setAppcode($appcode): ActiveKeep
    {
        $this->appcode = $appcode;
        return $this;
    }

    /**
     * 添加设备ID至缓存
     * @param string $device_id
     * @param int $day 活跃日期（Ymd - 20220406）
     * @param int $install_time 安装时间戳
     * @param int $active_time 激活时间戳
     * @return float
     */
    public function pushDevice(string $device_id, int $day, int $install_time, int $active_time): float
    {
        $key = $this->getCacheKey($day);
        $source = $this->Redis->zIncrBy($key, 1, $device_id);

        if ($source == 1) {//当天首次活跃,添加设备活跃记录

            $ttl = $this->Redis->ttl($key);
            if ($ttl < 0) {
                $this->Redis->expireAt($key, strtotime('+1 day'));
            }

            $tableActiveKeep = App::tableActiveKeep($this->appcode);
            $install_day = $install_time ? date('Ymd', $install_time) : 0;
            $install_keep_num = $install_day ? $this->getKeepDays((string)$day, $install_day) : 0;
            $activate_day = $active_time ? date('Ymd', $active_time) : 0;
            $activate_keep_num = $activate_day ? $this->getKeepDays((string)$day, $activate_day) : 0;
            $insert = [
                'device_id' => $device_id,
                'active_day' => $day,
                'install_day' => $install_day,
                'install_keep_num' => $install_keep_num,
                'activate_day' => $activate_day,
                'activate_keep_num' => $activate_keep_num,
                'created_time' => time()
            ];

            DB::table($tableActiveKeep)->insertOrIgnore($insert);

        }
        return $source;
    }

    /**
     * 留存
     * @param string $deviceId
     * @param int $adTime
     */
    public function eventKeep(string $deviceId, int $adTime)
    {

        $deviceInfo = DB::table(App::tableDevice($this->appcode))
            ->where('android_id', $deviceId)
            ->first();

        if (
            !empty($deviceInfo)
            && $deviceInfo['source'] > 0
            && $deviceInfo['ad_time'] > 0
            && $deviceInfo['ad_id'] > 0
            && $deviceInfo['keep_time'] == 0
            && date('Ymd', $deviceInfo['active_time']) == date('Ymd', $adTime - 86400)
        ) {
            $currTime = time();

            $tableAd = App::tableAdBySource($this->appcode, $deviceInfo['source']);
            // 更新记录
            DB::table($tableAd)
                ->where('id', '=', $deviceInfo['ad_id'])
                ->where('active_time', '>', 0)
                ->where('keep_time', '=', 0)
                ->update([
                    'keep_status' => 1,
                    'keep_time' => $adTime,
                    'update_time' => $currTime
                ]);
            DB::table(App::tableDevice($this->appcode))
                ->where('id', '=', $deviceInfo['id'])
                ->where('active_time', '>', 0)
                ->where('keep_time', 0)
                ->update([
                    'keep_time' => $adTime,
                    'update_time' => $currTime
                ]);
            dispatch((new NotifyAdKeepJob([
                'app_code' => $this->appcode,
                'device_id' => $deviceId,
                'keep_time' => $adTime,
            ]))->onQueue('{notify_ad_keep}'));
        }

    }

    /**
     * 获取缓存设备总数
     * @param int $day 日期（Ymd - 20220406）
     * @return int
     */
    public function countDevice(int $day): int
    {
        $key = $this->getCacheKey($day);
        return (int)$this->Redis->zCard($key);
    }

    /**
     * 获取缓存KEY
     * @param int $day 日期（Ymd - 20220406）
     * @return string
     */
    private function getCacheKey(int $day): string
    {
        return sprintf('%s:active:%s:%s', env('APP_NAME'), $this->appcode, $day);
    }

    /**
     * 获取广告上报缓存KEY
     * @param int $day 日期（Ymd - 20220406）
     * @return string
     */
    private function getAdCacheKey(int $day): string
    {
        return sprintf('%s:ad_report:%s:%s', env('APP_NAME'), $this->appcode, $day);
    }

    /**
     * 获取缓存设备
     * @param int $page
     * @param int $day
     * @return array
     */
    public function popDevice(int $page, int $day): array
    {
        $key = $this->getCacheKey($day);
        $start = ($page - 1) * self::PAGE_SIZE;
        $end = ($page * self::PAGE_SIZE) - 1;
        return $this->Redis->zRange($key, $start, $end, true);

    }


    /**
     * 清除设备缓存
     * @param int $day
     * @return void
     */
    public function clearDevice(int $day)
    {
        $key = $this->getCacheKey($day);
        $this->Redis->del($key);
    }


    /**
     * 批量移除设备缓存
     * @param int $day
     * @param array $device_list
     * @return void
     */
    public function removeDevice(int $day, array $device_list)
    {
        $key = $this->getCacheKey($day);
        array_unshift($device_list, $key);
        call_user_func_array([$this->Redis, 'zrem'], $device_list);
    }

    public function insertDevice(int $page, int $day)
    {
        $pop_data = $this->popDevice($page, $day);
        if (!$pop_data) {
            return;
        }
        $device_id_list = array_keys($pop_data);
        $time = time();

        $tableDevice = App::tableDevice($this->appcode);
        $tableActiveKeep = App::tableActiveKeep($this->appcode);
        $_device_data = DB::table($tableDevice)
            ->whereIn('android_id', $device_id_list)
            ->select(['android_id', 'create_time', 'active_time'])
            ->get()
            ->toArray();
        $device_data = [];
        foreach ($_device_data as $row) {
            $device_data[$row['android_id']] = $row;
        }

        $insert_keys = [
            'device_id',
            'active_day',
            'install_day',
            'install_keep_num',
            'activate_day',
            'activate_keep_num',
            'times',
            'created_time'
        ];
        $keys = implode(',', $insert_keys);

        $sql = "INSERT INTO {$tableActiveKeep} ( {$keys} ) VALUES ";

        foreach ($device_id_list as $device_id) {
            $device_info = $device_data[$device_id] ?? [];
            $install_day = isset($device_info['create_time']) && $device_info['create_time'] ? date('Ymd', $device_info['create_time']) : 0;
            $install_keep_num = $install_day ? $this->getKeepDays((string)$day, $install_day) : 0;
            $activate_day = isset($device_info['active_time']) && $device_info['active_time'] ? date('Ymd', $device_info['active_time']) : 0;
            $activate_keep_num = $activate_day ? $this->getKeepDays((string)$day, $activate_day) : 0;
            /*此处字段的顺序必须于上方$insert_keys保持一致*/
            $value = [];
            $value['device_id'] = $device_id;
            $value['active_day'] = $day;
            $value['install_day'] = $install_day;
            $value['install_keep_num'] = $install_keep_num;
            $value['activate_day'] = $activate_day;
            $value['activate_keep_num'] = $activate_keep_num;
            $value['times'] = $pop_data[$device_id];
            $value['created_time'] = $time;

            $value = implode("','", $value);
            $sql .= "('{$value}'),";
        }

        $sql = substr($sql, 0, -1);

        try {
            DB::insert($sql);
        } catch (\Exception $e) {
            LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
        }

    }

    /**
     * 计算留存天数
     * @param string $keep_day 留存日期
     * @param string $ago_day 之前的日期
     * @return false|float
     */
    private function getKeepDays(string $keep_day, string $ago_day)
    {
        return floor((strtotime($keep_day) - strtotime($ago_day)) / 86400);
    }

    /**
     * 处理留存数据
     * @param string $day
     * @param string $type
     * @return void
     */
    public function keepData(string $day, string $type)
    {
        $type_field_map = [
            'install' => [//安装对应字段
                'day_field' => 'a.install_day',
                'keep_num_field' => 'a.install_keep_num',

                'data_keep_field' => 'a.install_keep',
                'data_num_field' => 'a.install_num',
            ],
            'activate' => [//激活对应字段
                'day_field' => 'a.activate_day',
                'keep_num_field' => 'a.activate_keep_num',

                'data_keep_field' => 'a.activate_keep',
                'data_num_field' => 'a.activate_num',
            ],
        ];

        $type_field = $type_field_map[$type];
        $keep_json = App::keepJson();
        /*循环计算对应天数的留存数据*/
        foreach ($keep_json as $key => $item) {

            $ago_day = $item['ago_day'];
            $day_int = date('Ymd', strtotime($day) - (86400 * $ago_day));
            $day_str = date('Y-m-d', strtotime($day) - (86400 * $ago_day));
            $where = [];
            $where[] = [$type_field['day_field'], $day_int];
            $where[] = [$type_field['keep_num_field'], $ago_day];

            $levelArr = [
                'plan' => [],
                'ad' => [],
                'project' => [],
                'advertiser' => []
            ];

            foreach ($this->getKeepData($where) as $row) {
                if (empty($row['plan'])) {
                    continue;
                }
                // 巨量V3 项目、广告
                if (strstr($row['plan'], AdConst::PUT_PROJECT_AD_DELIMITER)) {
                    $aid = explode(AdConst::PUT_PROJECT_AD_DELIMITER, $row['plan']);
                    $row['project'] = $aid[0];
                    $row['ad'] = $aid[1];
                    $row['plan'] = '';
                }

                foreach ($levelArr as $levelKey => $levelValue) {
                    if (!empty($row[$levelKey])) {
                        Account::sumReportData($levelArr[$levelKey], strval($row[$levelKey]), $row, ['keep_num' => 0], 0);
                    }
                }
            }

            foreach ($levelArr as $levelKey => $levelValue) {
                foreach ($levelValue as $levelId => $fieldValue) {
                    $num = $this->levelTypeNum($day_str, $levelKey, strval($levelId), $type);
                    $rate = $num > 0 ? (round($fieldValue['keep_num'] / $num, 4) * 100) . '%' : '--';
                    $json = "json_object('keep_num','{$fieldValue['keep_num']}','ago_num','{$num}','keep_rate','{$rate}')";
                    $this->updateReportDaily($levelKey, $levelId, $day_str, [
                        "{$type}_keep" => DB::raw("json_set(if(isnull({$type}_keep),json_object(),{$type}_keep),'$.\"{$key}\"',{$json})")
                    ]);
                }
            }
        }

    }

    /**
     * 获取留存数据
     * @param $where
     * @return array
     */
    private function getKeepData($where): array
    {
        $activeKeepTable = App::tableActiveKeep($this->appcode);
        $deviceTable = App::tableDevice($this->appcode);

        return DB::table($activeKeepTable . ' as a')
            ->rightJoin($deviceTable . ' as b', 'a.device_id', '=', 'b.android_id')
            ->select([
                'b.aid as plan',
                'b.account_id as advertiser',
                DB::raw('count(a.device_id) as keep_num')
            ])
            ->where($where)
            ->groupBy(['b.account_id', 'b.aid'])
            ->get()
            ->toArray();
    }

    /**
     * 更新日报表
     * @param string $key
     * @param string $value
     * @param string $date
     * @param array $updateValues
     */
    protected function updateReportDaily($key, $value, $date, $updateValues)
    {
        Account::updateReportDaily($key, strval($value), $date, $updateValues);
    }

    /**
     * 层级的安装/激活数
     * @param string $date
     * @param string $level
     * @param string $levelId
     * @param string $type
     * @return int
     */
    protected function levelTypeNum(string $date, string $level, string $levelId, string $type)
    {
        return intval(DB::connection('uc')
            ->table("report_daily_{$level}_ext")
            ->where('id', DB::connection('uc')
                ->table("report_daily_{$level}")
                ->where('date', $date)
                ->where("{$level}_id", $levelId)
                ->value('id'))
            ->value("{$type}_num"));
    }
}