<?php
/**
 * 投放帐户通用
 */

namespace App\Services\Common;

use App\Constants\AdConst;
use Cache;
use DB;

class Account
{
    /**
     * 账户信息
     * @param $accountId
     * @return array
     */
    public static function info($accountId)
    {
        $key = 'account_info:' . $accountId;
        $data = Cache::get($key);

        if (is_null($data)) {
            $data = DB::connection('uc')->table('mkt_advertiser')
                ->where('advertiser_id', '=', $accountId)
                ->first();
            Cache::put($key, $data ?? [], 600);
        }
        return $data ?? [];
    }

    /**
     * 帐户额外配置
     * @param $accountId
     * @return array
     */
    public static function extra($accountId)
    {
        $accountInfo = self::info($accountId);

        $extra = $accountInfo['extra'] ?? null;

        return (array)json_decode($extra, true);
    }

    /**
     * 是否百分比扣量
     * @param $accountId
     * @param $timestamp
     * @return bool
     */
    public static function isDiscountPercent($accountId, $timestamp)
    {
        $accountInfo = self::info($accountId);

        $percent = (int)($accountInfo['notify_discount'] ?? 0);

        if ($percent <= 0) {
            return false;
        }

        return $timestamp % 100 < $percent;
    }

    /**
     * 是否关键行为
     * @param $accountId
     * @return bool
     */
    public static function isKeyAction($accountId)
    {
        $info = self::info($accountId);

        return ($info['launch_type'] ?? 0) == 1;
    }

    /**
     * 是否次留双出
     * @param $accountId
     * @return bool
     */
    public static function isActivateKeep($accountId)
    {
        $info = self::info($accountId);

        return ($info['launch_type'] ?? 0) == 0;
    }

    /**
     * 关键行为回收
     * @param $accountId
     * @return array
     */
    public static function actionRevenue($accountId)
    {
        $info = self::info($accountId);
        return [
            'action_type' => $info['action_type'] ?? 0,// [1]百分比[2]固定金额
            'action_revenue' => $info['action_revenue'] ?? 0,// 回收数值
        ];
    }

    /**
     * 关键行为目标
     * @param $accountId
     * @return array
     */
    public static function actionValue($accountId)
    {
        $info = self::info($accountId);
        $value = json_decode($info['action_value'] ?? '', true);
        return [
            'action_type' => $info['action_type'],
            'action_value' => $value,
        ];
    }

    /**
     * 是否24小时留存
     * @param $accountId
     * @return bool
     */
    public static function keep24($accountId)
    {
        $info = self::info($accountId);
        return ($info['keep_type'] ?? 0) == 1;
    }

    /**
     * 扣量二次补充
     * @param $accountId
     * @return bool
     */
    public static function notifyFill($accountId)
    {
        $info = self::info($accountId);
        return ($info['notify_fill'] ?? 0) == 1;
    }

    /**
     * 激活回收
     * @param $accountId
     * @return array
     */
    public static function activeValue($accountId)
    {
        $info = self::info($accountId);
        $value = json_decode($info['active_value'] ?? '', true);
        return [
            'active_type' => $info['active_type'],
            'active_value' => $value
        ];
    }

    /**
     * 激活成本
     * @param string $date
     * @param string $planId
     * @return float
     */
    public static function activeCost(string $date, string $planId)
    {
        if (strstr($planId, AdConst::PUT_PROJECT_AD_DELIMITER)) {
            $id = $v['ad'] = explode(AdConst::PUT_PROJECT_AD_DELIMITER, $planId)[1];
            $level = 'ad';
        } else {
            $id = $planId;
            $level = 'plan';
        }
        return round(DB::connection('uc')
            ->table("report_daily_{$level}")
            ->where('date', $date)
            ->where("{$level}_id", $id)
            ->value('active_cost'), 4);
    }

    /**
     * 更新日报表
     * @param string $level advertiser/project/ad/plan
     * @param string $levelId
     * @param string $date
     * @param array $updateValues
     */
    public static function updateReportDaily(string $level, string $levelId, string $date, array $updateValues)
    {
        $id = DB::connection('uc')
            ->table("report_daily_{$level}")
            ->where('date', $date)
            ->where("{$level}_id", $levelId)
            ->value('id');
        // 荣耀特殊渠道处理
        if (!$id && $appCode = strstr($levelId, '-channel-', true)) {
            $id = DB::connection('uc')
                ->table("report_daily_{$level}")
                ->insertGetId([
                    'app_code' => $appCode,
                    "{$level}_id" => $levelId,
                    'date' => $date
                ]);
        }
        if ($id) {
            $attributes = [
                'id' => $id
            ];
            DB::connection('uc')
                ->table("report_daily_{$level}_ext")
                ->updateOrInsert($attributes, $updateValues);
        }
    }

    /**
     * 日报表字短数据汇总
     * @param array $level
     * @param string $levelId
     * @param array $data
     * @param array $keyData
     * @param int $precision
     */
    public static function sumReportData(array &$level, string $levelId, array $data, array $keyData, $precision = 2)
    {
        if (!isset($level[$levelId])) {
            $level[$levelId] = $keyData;
        }
        foreach ($keyData as $field => $value) {
            if (isset($level[$levelId][$field]) && isset($data[$field])) {
                $level[$levelId][$field] = round($level[$levelId][$field] + $data[$field], $precision);
            }
        }
    }
}
