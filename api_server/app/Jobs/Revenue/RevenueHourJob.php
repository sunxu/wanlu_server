<?php
/**
 * 回收-小时维度
 */

namespace App\Jobs\Revenue;

use App\Constants\AdConst;
use App\Services\Common\Account;
use App\Services\Common\App;
use App\Jobs\BaseJob;
use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;

class RevenueHourJob extends BaseJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (empty($this->data['app_code']) || empty($this->data['hour'])) {
            return;
        }
        $handleStartTime = microtime(true);

        $appcode = $this->data['app_code'];
        $hour = date('H', strtotime($this->data['hour']));
        $start = date('Y-m-d H:00:00', strtotime($this->data['hour']));
        $end = date('Y-m-d H:00:00', strtotime('+1 hour', strtotime($start)));
        $date = date('Y-m-d', strtotime($start));

        $tableDevice = App::tableDevice($appcode);
        $tableRevenue = App::tableEventAdRevenue($appcode);

        $keyData = [
            'hour_revenue' => 0,
        ];

        $list = DB::table("{$tableRevenue} as a")
            ->select([
                DB::raw("SUM(a.amount) as hour_revenue"),
                'b.account_id as advertiser',
                'b.aid as plan'
            ])
            ->leftJoin("{$tableDevice} as b", 'b.android_id', '=', 'a.device_id')
            ->where('a.time', '>=', date('YmdHis', strtotime($start)))
            ->where('a.time', '<', date('YmdHis', strtotime($end)))
            ->groupBy(['b.account_id', 'b.aid'])
            ->get()
            ->toArray();

        $levelArr = [
            'plan' => [],
            'ad' => [],
            'project' => [],
            'advertiser' => []
        ];

        foreach ($list as $v) {
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
        // 循环入库
        foreach ($levelArr as $levelKey => $levelValue) {
            foreach ($levelValue as $levelId => $fieldValue) {
                try {
                    $this->updateReportDaily($levelKey, $levelId, $date, $this->dataFormat($fieldValue, $hour));
                } catch (Exception $e) {
                    LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
                }
            }
        }

        echo 'revenue-hour' . json_encode([
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
     * 数据入库格式化
     * @param array $v
     * @param $hour
     * @return array
     */
    protected function dataFormat(array $v, string $hour)
    {
        return [
            'hour_revenue' => DB::raw("json_set(if(isnull(hour_revenue),json_object(),hour_revenue),'$.\"{$hour}\"',{$v['hour_revenue']})")
        ];
    }

}
