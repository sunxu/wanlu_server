<?php
/**
 * 广告数据|广告打点、设备数
 */

namespace App\Console\Commands\Statistics;

use App\Constants\AdConst;
use App\Services\Common\Account;
use App\Services\Common\App;
use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;
use Illuminate\Console\Command;

class LocalDataAd extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'local-data-ad {date} {appcode?*}';

    /**
     * Command description
     * @var string
     */
    protected $description = '本地广告数据';

    /**
     * 表格表头
     * @var array
     */
    protected $tableHeader = [
        '应用',
        '广告主',
        '广告项目',
        '广告',
        '广告计划',
        '执行时间'
    ];

    /**
     * 表格内容
     * @var array
     */
    protected $tableBody = [];

    /**
     * Command Handle
     */
    public function handle()
    {
        // 开始时间
        $startTime = microtime(true);
        $arguments = $this->arguments();
        $this->comment('[' . date('YmdHis') . ' start] ' . implode(' ', array_map(function ($v) {
                return is_array($v) ? implode(' ', $v) : $v;
            }, $arguments)));
        $date = $arguments['date'];
        if (strtotime($date) < strtotime('-2 days')) {
            echo "超过2日历史数据不可覆盖" . PHP_EOL;
        }
        $dateStartTime = strtotime($date);
        $dateEndTime = strtotime($date . ' 23:59:59');
        // 工具-自投
        $apps = App::codeList([data_get(App::$appType, 'tool')], $arguments['appcode'] ?? [], [data_get(App::$adStatus, 'self')]);
        foreach ($apps as $appcode => $appname) {
            $appStartTime = microtime(true);
            $tableAdRequest = App::tableEventAdRequest($appcode);
            $tableAdFull = App::tableEventAdFull($appcode);
            $tableAdShow = App::tableEventAdShow($appcode);
            $tableAdClick = App::tableEventAdClick($appcode);
//            $tableLock = App::tableEventInformation($appcode);
//            $tableLog = App::tableLogDevice($appcode);
            $tableDevice = App::tableDevice($appcode);

            $keyData = [
                // 设备
//                'install' => 0,// 安装-新安装,
//                'activate' => 0,// 激活-新激活
//                'active' => 0,// 活跃-总数
//                'active_new' => 0, // 活跃-新增
                // 安装设备
                'install_request' => 0,
                'install_fill' => 0,
                'install_show' => 0,
                'install_click' => 0,
                // 活跃设备
                'active_request' => 0,
                'active_fill' => 0,
                'active_show' => 0,
                'active_click' => 0,
                // 激活设备
                'activate_show' => 0,
                'activate_click' => 0,
                // 锁屏
                'install_lock_show' => 0,
                'active_lock_show' => 0,
                'activate_lock_show' => 0,
            ];
//            // [1]当日设备数据
//            $list['device'] = DB::table("{$tableLog} as l")
//                ->select([
//                    DB::raw('SUM(IF(l.ad_time > 0,1,0)) as active'),
//                    DB::raw("SUM(IF(d.create_time >= {$dateStartTime} AND d.create_time <= {$dateEndTime},1,0)) as install"),
//                    DB::raw("SUM(IF(d.create_time >= {$dateStartTime} AND d.create_time <= {$dateEndTime} AND l.ad_time > 0,1,0)) as active_new"),
//                    DB::raw("SUM(IF(d.active_time >= {$dateStartTime} AND d.active_time <= {$dateEndTime},1,0)) as activate"),
//                    'd.account_id',
//                    'd.aid',
//                ])
//                ->leftJoin("{$tableDevice} as d", 'l.device_id', '=', 'd.android_id')
//                ->where('l.date', $date)
//                ->groupBy('d.aid')
//                ->get()
//                ->toArray();

            // [2]广告打点
            $list['ad_request'] = DB::table(DB::raw($this->tempTable($tableAdRequest, $dateStartTime, $dateEndTime) . " as a"))
                ->select([
                    'd.aid as plan',
                    'd.account_id as advertiser',
                    DB::raw("SUM(IF(d.create_time >= {$dateStartTime} AND d.create_time <= $dateEndTime,a.times,0)) as install_request"),
                    DB::raw("SUM(a.times) as active_request")
                ])
                ->leftJoin("{$tableDevice} as d", 'a.android_id', '=', 'd.android_id')
                ->groupBy(['d.account_id', 'd.aid'])
                ->get()
                ->toArray();

            $list['ad_fill'] = DB::table(DB::raw($this->tempTable($tableAdFull, $dateStartTime, $dateEndTime) . " as a"))
                ->select([
                    'd.aid as plan',
                    'd.account_id as advertiser',
                    DB::raw("SUM(IF(d.create_time >= {$dateStartTime} AND d.create_time <= $dateEndTime,a.times,0)) as install_fill"),
                    DB::raw("SUM(a.times) as active_fill")
                ])
                ->leftJoin("{$tableDevice} as d", 'a.android_id', '=', 'd.android_id')
                ->groupBy(['d.account_id', 'd.aid'])
                ->get()
                ->toArray();

            $list['ad_show'] = DB::table(DB::raw($this->tempTable($tableAdShow, $dateStartTime, $dateEndTime) . " as a"))
                ->select([
                    'd.aid as plan',
                    'd.account_id as advertiser',
                    DB::raw("SUM(IF(d.create_time >= {$dateStartTime} AND d.create_time <= $dateEndTime,a.times,0)) as install_show"),
                    DB::raw("SUM(IF(d.active_time >= {$dateStartTime} AND d.active_time <= $dateEndTime,a.times,0)) as activate_show"),
                    DB::raw("SUM(a.times) as active_show"),
                ])
                ->leftJoin("{$tableDevice} as d", 'a.android_id', '=', 'd.android_id')
                ->groupBy(['d.account_id', 'd.aid'])
                ->get()
                ->toArray();

            $list['ad_click'] = DB::table(DB::raw($this->tempTable($tableAdClick, $dateStartTime, $dateEndTime) . " as a"))
                ->select([
                    'd.aid as plan',
                    'd.account_id as advertiser',
                    DB::raw("SUM(IF(d.create_time >= {$dateStartTime} AND d.create_time <= $dateEndTime,a.times,0)) as install_click"),
                    DB::raw("SUM(IF(d.active_time >= {$dateStartTime} AND d.active_time <= $dateEndTime,a.times,0)) as activate_click"),
                    DB::raw("SUM(a.times) as active_click"),
                ])
                ->leftJoin("{$tableDevice} as d", 'a.android_id', '=', 'd.android_id')
                ->groupBy(['d.account_id', 'd.aid'])
                ->get()
                ->toArray();

            $levelArr = [
                'plan' => [],
                'ad' => [],
                'project' => [],
                'advertiser' => []
            ];
            foreach ($list as $_list) {
                foreach ($_list as $v) {
                    // 巨量V3 项目、广告
                    if (strstr($v['plan'], AdConst::PUT_PROJECT_AD_DELIMITER)) {
                        $aid = explode(AdConst::PUT_PROJECT_AD_DELIMITER, $v['plan']);
                        $v['project'] = $aid[0];
                        $v['ad'] = $aid[1];
                        $v['plan'] = '';
                    }
                    foreach ($levelArr as $levelKey => $levelValue) {
                        if (!empty($v[$levelKey])) {
                            Account::sumReportData($levelArr[$levelKey], strval($v[$levelKey]), $v, $keyData);
                        }
                    }
                }
            }

            // [4]循环入库
            foreach ($levelArr as $levelKey => $levelValue) {
                foreach ($levelValue as $levelId => $fieldValue) {
                    try {
                        $this->updateReportDaily($levelKey, $levelId, $date, $this->dataFormat($fieldValue));
                    } catch (Exception $e) {
                        LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
                    }
                }
            }
            $this->tableBody[] = [
                $appcode,
                count($levelArr['advertiser']),
                count($levelArr['project']),
                count($levelArr['ad']),
                count($levelArr['plan']),
                sprintf('%.3fs', microtime(true) - $appStartTime)
            ];
        }
        $this->table($this->tableHeader, $this->tableBody);
        $this->comment('[' . date('YmdHis') . ' end] ' . implode(' ', array_map(function ($v) {
                return is_array($v) ? implode(' ', $v) : $v;
            }, $arguments)));
    }

    /**
     * 数据入库格式化
     * @param array $v
     * @return array
     */
    protected function dataFormat(array $v)
    {
        return [
//            'install_num' => $v['install'],
//            'active_num' => $v['active'],
//            'active_new' => $v['active_new'],
//            'activate_num' => $v['activate'],

            'install_request' => $v['install_request'],
            'install_fill' => $v['install_fill'],
            'install_fill_rate' => $v['install_request'] > 0 ? round($v['install_fill'] * 100 / $v['install_request'], 2) : 0,
            'install_show' => $v['install_show'],
//            'install_avg_show' => $v['install'] > 0 ? round($v['install_show'] / $v['install'], 2) : 0,
            'install_show_rate' => $v['install_fill'] > 0 ? round($v['install_show'] * 100 / $v['install_fill'], 2) : 0,
//            'install_click' => $v['install_click'],

            'active_request' => $v['active_request'],
            'active_fill' => $v['active_fill'],
            'active_fill_rate' => $v['active_request'] > 0 ? round($v['active_fill'] * 100 / $v['active_request'], 2) : 0,
            'active_show' => $v['active_show'],
            'active_show_rate' => $v['active_fill'] > 0 ? round($v['active_show'] * 100 / $v['active_fill'], 2) : 0,
//            'active_click' => $v['active_click'],

            'activate_show' => $v['activate_show'],
//            'activate_avg_show' => $v['activate'] > 0 ? round($v['activate_show'] / $v['activate'], 2) : 0,
//            'activate_click' => $v['activate_click'],

//            'install_lock_show' => $v['install_lock_show'],
//            'active_lock_show' => $v['active_lock_show'],
//            'activate_lock_show' => $v['activate_lock_show'],
//            'update_time' => time()
        ];
    }

    /**
     * 命令调用
     * @param array $arguments
     */
    protected function callArtisan(array $arguments)
    {
        // 收益报表|穿山甲、腾讯、快手
//        $this->call('report-csj', ['date' => $arguments['date']]);
        // 设备价值
//        $this->call('local-device-revenue', ['date' => $arguments['date'], 'appcode' => $arguments['appcode']]);
        // 收益统计
//        $this->call('local-data-revenue', ['date' => $arguments['date'], 'appcode' => $arguments['appcode']]);
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
     * 广告打点分组临时表
     * @param string $table
     * @param int $startTimestamp
     * @param int $endTimestamp
     * @return string
     */
    protected function tempTable(string $table, int $startTimestamp, int $endTimestamp)
    {
        return "
            (SELECT
                count( uuid ) as times,
                android_id 
            FROM
                {$table} 
            WHERE
                `create_time` BETWEEN {$startTimestamp} 
                AND {$endTimestamp} 
            GROUP BY android_id)
        ";
    }
}
