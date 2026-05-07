<?php
declare(strict_types=1);
/**
 * 回本率统计
 * Author: jyk-wsy
 * Date: 2022/4/7
 */

namespace App\Services\Common;

use App\Constants\AdConst;
use Illuminate\Support\Facades\DB;

class Recovery
{

    private $appcode;

    private static $instance = null;


    /**
     * @return Recovery|null
     */
    public static function getInstance(): ?Recovery
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
    public function setAppcode($appcode): Recovery
    {
        $this->appcode = $appcode;
        return $this;
    }

    public function recoveryData($date)
    {
        $recoveryJson = App::recoveryJson();
        $deviceTable = App::tableDevice($this->appcode);
        $tableRevenue = App::tableEventAdRevenue($this->appcode);

        $dateTimestamp = strtotime($date);

        $temp = $dateTimestamp < strtotime(date('Y-m-d 00:00:00', strtotime('-1 day'))) ? '_temp' : '';

        $tableRevenue = $tableRevenue . $temp;

        foreach ($recoveryJson as $key => $item) {

            $agoDay = $item['ago_day'];

            $appDate = date('Y-m-d', $dateTimestamp - (86400 * $agoDay));

            $appDateTimestamp = strtotime($appDate);

            $where = [];
            $where[] = ['b.create_time', '>=', $appDateTimestamp];
            $where[] = ['b.create_time', '<', $appDateTimestamp + 86400];

            $where[] = ['a.time', '>=', date('YmdHis', $dateTimestamp)];
            $where[] = ['a.time', '<', date('YmdHis', $dateTimestamp + 86400)];

            $recoveryData = DB::table($tableRevenue . ' as a')
                ->leftJoin($deviceTable . ' as b', 'a.device_id', '=', 'b.android_id')
                ->where($where)
                ->select([
                    'b.aid as plan',
                    'b.account_id as advertiser',
                    DB::raw('sum(a.amount) as revenue')
                ])
                ->groupBy(['b.account_id', 'b.aid'])
                ->get()
                ->toArray();

            $levelArr = [
                'plan' => [],
                'ad' => [],
                'project' => [],
                'advertiser' => []
            ];

            foreach ($recoveryData as $row) {
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
                        Account::sumReportData($levelArr[$levelKey], strval($row[$levelKey]), $row, ['revenue' => 0]);
                    }
                }
            }

            foreach ($levelArr as $levelKey => $levelValue) {
                foreach ($levelValue as $levelId => $fieldValue) {
                    $cost = $this->levelCost($appDate, $levelKey, strval($levelId));
                    $rate = $cost > 0 ? (round($fieldValue['revenue'] / $cost, 4) * 100) . '%' : '--';
                    $json = "json_object('revenue','{$fieldValue['revenue']}','cost','{$cost}','recovery_rate','{$rate}')";
                    $this->updateReportDaily($levelKey, $levelId, $appDate, [
                        'recovery' => DB::raw("json_set(if(isnull(recovery),json_object(),recovery),'$.\"{$key}\"',{$json})")
                    ]);
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
     * 层级成本
     * @param string $date
     * @param string $level
     * @param string $levelId
     * @return float
     */
    protected static function levelCost(string $date, string $level, string $levelId)
    {
        return round(DB::connection('uc')
            ->table("report_daily_{$level}")
            ->where('date', $date)
            ->where("{$level}_id", $levelId)
            ->value('cost'), 4);
    }
}