<?php
/**
 * 回收-天维度
 */

namespace App\Jobs\Revenue;

use App\Constants\AdConst;
use App\Services\Common\Account;
use App\Services\Common\App;
use App\Jobs\BaseJob;
use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;
use Schema;

class RevenueDayJob extends BaseJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (empty($this->data['app_code']) || empty($this->data['date'])) {
            return;
        }
        $handleStartTime = microtime(true);

        $date = $this->data['date'];
        $appcode = $this->data['app_code'];

        $dateStartTime = strtotime($date);
        $dateEndTime = strtotime($date . ' 23:59:59');

        $tableActiveKeep = App::tableActiveKeep($appcode);
        $tableDevice = App::tableDevice($appcode);
        $tableTrade = App::tableTrade($appcode);
        $tableRevenue = App::tableEventAdRevenue($appcode);

        $keyData = [
            // 设备
            'install' => 0,// 安装-新安装,
            'activate' => 0,// 激活-新激活
            'active' => 0,// 活跃-总数
            'active_new' => 0, // 活跃-新增

            'install_revenue' => 0,
            'activate_revenue' => 0,
            'active_revenue' => 0,

            'install_payment' => 0,
            'active_payment' => 0,
        ];

        $list['device'] = DB::table("{$tableActiveKeep} as l")
            ->select([
                DB::raw('SUM(IF(l.is_ad_report > 0,1,0)) as active'),
                DB::raw("SUM(IF(d.create_time >= {$dateStartTime} AND d.create_time <= {$dateEndTime},1,0)) as install"),
                DB::raw("SUM(IF(d.create_time >= {$dateStartTime} AND d.create_time <= {$dateEndTime} AND l.is_ad_report > 0,1,0)) as active_new"),
                DB::raw("SUM(IF(d.active_time >= {$dateStartTime} AND d.active_time <= {$dateEndTime},1,0)) as activate"),
                'd.account_id as advertiser',
                'd.aid as plan',
            ])
            ->leftJoin("{$tableDevice} as d", 'l.device_id', '=', 'd.android_id')
            ->where('l.active_day', date('Ymd', strtotime($date)))
            ->groupBy(['d.account_id', 'd.aid'])
            ->get()
            ->toArray();
        // [2]回收数据
        $list['revenue'] = DB::table("{$tableRevenue} as r")
            ->select([
                DB::raw("SUM(IF(d.create_time >= {$dateStartTime} AND d.create_time <= {$dateEndTime},r.amount,0)) as install_revenue"),
                DB::raw("SUM(IF(d.active_time >= {$dateStartTime} AND d.active_time <= {$dateEndTime},r.amount,0)) as activate_revenue"),
                DB::raw("SUM(r.amount) as active_revenue"),
                'd.account_id as advertiser',
                'd.aid as plan',
            ])
            ->leftJoin("{$tableDevice} as d", 'r.device_id', '=', 'd.android_id')
            ->where('r.time', '>=', date('YmdHis', $dateStartTime))
            ->where('r.time', '<', date('YmdHis', $dateEndTime))
            ->groupBy(['d.account_id', 'd.aid'])
            ->get()
            ->toArray();

        if (Schema::hasTable($tableTrade)) {
            // [3]支付数据
            $list['payment'] = DB::table("{$tableTrade} as t")
                ->select([
                    DB::raw("SUM(IF(d.create_time >= {$dateStartTime} AND d.create_time <= {$dateEndTime},t.amount,0)) as install_payment"),
                    DB::raw("SUM(IF(d.active_time >= {$dateStartTime} AND d.active_time <= {$dateEndTime},t.amount,0)) as activate_payment"),
                    DB::raw("SUM(t.amount) as active_payment"),
                    'd.account_id as advertiser',
                    'd.aid as plan',
                ])
                ->leftJoin("{$tableDevice} as d", 't.account', '=', 'd.android_id')
                ->where('t.trade_status', '=', 1)
                ->where('t.created_time', '>=', $dateStartTime)
                ->where('t.created_time', '<=', $dateEndTime)
                ->groupBy(['d.account_id', 'd.aid'])
                ->get()
                ->toArray();
        }
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

        echo 'revenue-day' . json_encode([
                'data' => $this->data,
                'result' => [
                    'advertiser' => count($levelArr['advertiser']),
                    'project' => count($levelArr['project']),
                    'ad' => count($levelArr['ad']),
                    'plan' => count($levelArr['plan']),
                ],
                'time' => sprintf('%.3fs', microtime(true) - $handleStartTime)
            ], 320) . PHP_EOL;
    }

    /**
     * 数据入库格式化
     * @param array $v
     * @return array
     */
    protected function dataFormat(array $v)
    {
        return [
            'install_num' => $v['install'],
            'active_num' => $v['active'],
            'active_new' => $v['active_new'],
            'activate_num' => $v['activate'],

            'install_revenue' => $v['install_revenue'],
            'install_avg_revenue' => round($v['install'] > 0 ? $v['install_revenue'] / $v['install'] : 0, 4),
            'activate_revenue' => $v['activate_revenue'],
            'active_revenue' => $v['active_revenue'],

            'install_payment' => $v['install_payment'],
            'active_payment' => $v['active_payment'],
            'update_time' => time()
        ];
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
}
