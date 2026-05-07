<?php

declare(strict_types=1);
/**
 * 24小时ROI计算
 * Author: wangshenyuan
 * Date: 2022/2/28
 */

namespace App\Jobs;

use App\Constants\AdConst;
use App\Services\Common\Account;
use App\Services\Common\App;
use App\Services\Helper\Tools\Dding;
use Illuminate\Support\Facades\DB;

class Roi24Job extends BaseJob
{
    public function handle()
    {

        try {
            $day = $this->data['day'];
            $type = $this->data['type'];
            $appcode = $this->data['appcode'];
            $typeMap = [
                'install' => ['where_field' => 'b.create_time', 'data_field' => 'install_revenue24'],//安装
                'activate' => ['where_field' => 'b.active_time', 'data_field' => 'activate_revenue24'],//激活
            ];

            if (!isset($typeMap[$type])) {
                return;
            }

            $whereField = $typeMap[$type]['where_field'];
            $dataField = $typeMap[$type]['data_field'];

            $dayTime = strtotime($day);

            $tableDevice = App::tableDevice($appcode);
            $tableIntervalGroup = App::tableIntervalGroup($appcode);
            $tableRevenue = App::tableEventAdRevenue($appcode);

            $where = [];
            $where[] = [$whereField, '>=', $dayTime];
            $where[] = [$whereField, '<', $dayTime + 86400];

//            if (in_array($appcode, config('ad.test_app', []))) {
//                $where[] = ['a.time', '>=', DB::raw("FROM_UNIXTIME({$whereField}, '%Y%m%d%H%i%s')")];
                $where[] = ['a.time', '<', DB::raw("FROM_UNIXTIME({$whereField}+86400, '%Y%m%d%H%i%s')")];

                $list = DB::table("{$tableRevenue} as a")
                    ->select([
                        DB::raw("SUM(amount) as {$dataField}"),
                        'b.account_id as advertiser',
                        'b.aid as plan'
                    ])
                    ->leftJoin("{$tableDevice} as b", 'b.android_id', '=', 'a.device_id')
                    ->where($where)
                    ->groupBy(['b.account_id', 'b.aid'])
                    ->get()
                    ->toArray();
//            } else {
////                $where[] = ['a.start_time', '>=', DB::raw($whereField)];
//                $where[] = ['a.end_time', '<=', DB::raw($whereField . ' + 86400')];
//                $list = DB::table("{$tableIntervalGroup} as a")
//                    ->select([
//                        DB::raw("SUM(amount) as {$dataField}"),
//                        'b.account_id as advertiser',
//                        'b.aid as plan'
//                    ])
//                    ->leftJoin("{$tableDevice} as b", 'b.android_id', '=', 'a.device_id')
//                    ->where($where)
//                    ->groupBy(['b.account_id', 'b.aid'])
//                    ->get()
//                    ->toArray();
//            }

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
                        Account::sumReportData($levelArr[$levelKey], strval($v[$levelKey]), $v, [$dataField => 0]);
                    }
                }
            }

            // 循环入库
            foreach ($levelArr as $levelKey => $levelValue) {
                foreach ($levelValue as $levelId => $fieldValue) {
                    $this->updateReportDaily($levelKey, $levelId, $day, $fieldValue);
                }
            }

        } catch (\Exception $e) {
            $this->release(1 * $this->attempts());
            echo $e->getMessage();
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
     * @param \Exception $exception
     * @return void
     */
    public function failed(\Exception $exception)
    {
        $error_msg = '【任务队列：' . $this->queue . '】尝试' . $this->tries . '次依然失败，请检查' . PHP_EOL;
        $error_msg .= '任务参数：' . json_encode($this->data);
        $error_msg .= 'error：' . $exception->getMessage();
//        echo $error_msg;
        Dding::push($error_msg);
    }
}
