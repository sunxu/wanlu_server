<?php
/**
 * 广告统计|用户24小时收入/成本判定
 */

namespace App\Jobs;

use App\Services\Common\Account;
use App\Services\Common\App;
use App\Services\Helper\Helper;
use App\Services\Helper\Tools\LogPush;
use Cache;
use DB;

class AdRevenueJob extends BaseJob
{
    /**
     * 失败执行次数|每小时一次
     * @var int
     */
    public $tries = 48;

    /**
     * 频次|分钟
     * @var int
     */
    public $minutes = 30;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 设备成本|激活日
        $cost = Account::activeCost(date('Y-m-d', $this->data['start_time']), $this->data['plan_id']);
        // 设备信息
        $device = DB::table(App::tableDevice($this->data['app_code']))
            ->where('android_id', '=', $this->data['device_id'])
            ->first(['id', 'version']);
        // 回收
        $revenue = $this->deviceRevenue();
        $currTime = time();
        // 更新收入
        DB::table(App::tableDevice($this->data['app_code']))
            ->where('id', '=', $device['id'])
            ->update([
                'action_info' => json_encode([
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'time' => $currTime
                ], 320),
                'update_time' => $currTime
            ]);
        // 账户回收目标
        $account = Account::actionRevenue($this->data['account_id']);
        // 错误账户配置
        if (empty($account) || !in_array($account['action_type'], [1, 2]) || $account['action_revenue'] == 0) {
            return;
        }
        // 不满足关键行为
        if ($cost <= 0 || ($account['action_type'] == 1 && round($revenue * 100 / $cost, 2) < $account['action_revenue']) || ($account['action_type'] == 2 && $revenue < $account['action_revenue'])) {
            // 未达标
            if ($this->attempts() < $this->tries && $currTime < $this->data['end_time']) {
                $this->release($this->minutes * 60);
            }
            return;
        }
        // 关键行为时间
        $actionTime = DB::table(App::tableDevice($this->data['app_code']))
            ->where('android_id', '=', $this->data['device_id'])
            ->value('action_time');
        if ($actionTime > 0) {
            return;
        }

        // 满足关键行为
        $tableAd = App::tableAdBySource($this->data['app_code'],$this->data['source']);
        DB::table($tableAd)
            ->where('id', '=', $this->data['ad_id'])
            ->update([
                'action_status' => 1,
                'action_time' => $currTime,
                'update_time' => $currTime
            ]);
        DB::table(App::tableDevice($this->data['app_code']))
            ->where('android_id', '=', $this->data['device_id'])
            ->where('action_time', '=', 0)
            ->update([
                'action_time' => $currTime,
                'update_time' => $currTime
            ]);
        dispatch((new NotifyAdActionJob([
            'app_code' => $this->data['app_code'],
            'device_id' => $this->data['device_id'],
            'account_id' => $this->data['account_id'],
            'source' => $this->data['source'],
            'ad_id' => $this->data['ad_id'],
            'action_time' => $currTime,
            'active_time' => $this->data['start_time'],
            'revenue' => $revenue,
            'cost' => $cost,
        ]))->onQueue('{notify_ad_action}'));

        // 后续计算截止到24小时数据
        if ($this->data['end_time'] - time() > 600) {
            $this->release($this->data['end_time'] - time());
        }
    }

    /**
     * 设备回收
     */
    public function deviceRevenue()
    {
        $revenue = DB::table(App::tableIntervalGroup($this->data['app_code']))
            ->where('device_id', $this->data['device_id'])
//            ->where('start_time', '>=', $this->data['start_time'])
            ->where('end_time', '<=', $this->data['end_time'])
            ->value(DB::raw("SUM(amount)"));
        return round($revenue, 4);
    }
}
