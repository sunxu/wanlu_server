<?php

namespace App\Jobs\Revenue;

use App\Services\Common\Account;
use App\Services\Common\App;
use App\Jobs\BaseJob;
use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;

class SumHourJob extends BaseJob
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

        $appcode = $this->data['app_code'];
        $hour = date('H', strtotime($this->data['hour']));
        $startTimestamp = strtotime(date('Y-m-d H:00:00', strtotime($this->data['hour'])));
        $endTimestamp = strtotime(date('Y-m-d H:00:00', strtotime('+1 hour', $startTimestamp)));
        $date = date('Y-m-d', $startTimestamp);


        $tableDevice = App::tableDevice($appcode);
        $tableRevenue = App::tableEventAdRevenue($appcode);

        $keyData = [
            'install' => 0,// 安装-新安装,
            'install_revenue' => 0,
        ];

        $list['device'] = DB::table("{$tableDevice}")
            ->select([
                DB::raw("count(*) as install"),
                'account_id as advertiser',
            ])
            ->where('create_time', '>=', $startTimestamp)
            ->where('create_time', '<', $endTimestamp)
            ->groupBy('account_id')
            ->get()
            ->toArray();

        $list['revenue'] = DB::table("{$tableDevice} as d")
            ->select([
                DB::raw("SUM(r.amount) as install_revenue"),
                'd.account_id as advertiser',
            ])
            ->leftJoin("{$tableRevenue} as r", 'r.device_id', '=', 'd.android_id')
            ->where('d.create_time', '>=', strtotime($date))
            ->where('d.create_time', '<', strtotime($date) + 86400)
            ->where('r.time', '>=', date('YmdHis', $startTimestamp))
            ->where('r.time', '<', date('YmdHis', $endTimestamp))
            ->groupBy(['d.account_id'])
            ->get()
            ->toArray();

        $levelArr = [
            'advertiser' => []
        ];

        foreach ($list as $_list) {
            foreach ($_list as $v) {
                foreach ($levelArr as $levelKey => $levelValue) {
                    if (!empty($v[$levelKey])) {
                        Account::sumReportData($levelArr[$levelKey], strval($v[$levelKey]), $v, $keyData);
                    }
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
     * @param array $fieldValue
     * @param $hour
     * @return array
     */
    protected function dataFormat(array $fieldValue, string $hour)
    {
        $json = "json_object('install','{$fieldValue['install']}','install_revenue','{$fieldValue['install_revenue']}')";
        return [
            'hour_sum' => DB::raw("json_set(if(isnull(hour_sum),json_object(),hour_sum),'$.\"{$hour}\"',{$json})")
        ];
    }
}
