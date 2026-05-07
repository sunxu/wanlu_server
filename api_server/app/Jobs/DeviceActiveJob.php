<?php
/**
 * 设备激活
 */

namespace App\Jobs;

use App\Services\Common\Account;
use App\Services\Common\Active;
use App\Services\Common\App;
use DB;

class DeviceActiveJob extends BaseJob
{

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        echo json_encode($this->data, 320) . PHP_EOL;
        $device = DB::table(App::tableDevice($this->data['app_code']))
            ->useWritePdo()
            ->where('android_id', $this->data['device_id'])
            ->first([
                'id',
                'ad_id',
                'account_id',
                'aid as plan_id',
                'source',
                'active_time',
            ]);
        if (empty($device['ad_id']) || empty($device['source'])) {
            echo 'empty ad_id' . PHP_EOL;
            if ($this->attempts() < $this->tries) {
                $this->release(10 * $this->attempts());
            }
            return;
        }
        if (!empty($device['active_time'])) {
            echo 'active_time > 0' . PHP_EOL;
            return;
        }
        // 非补量且激活扣量
        if (!($this->data['notify'] ?? false) && Account::isDiscountPercent($device['account_id'] ?? 0, $this->data['active_time'])) {
            // 扣量二次判定
            if (Account::notifyFill($device['account_id'] ?? 0)) {
                Active::setNotifyFill($this->data['app_code'], $this->data['device_id']);
            }
            return;
        }

        $tableAd = App::tableAdBySource($this->data['app_code'], $device['source']);
        DB::beginTransaction();
        $result1 = DB::table(App::tableDevice($this->data['app_code']))
            ->where('id', $device['id'])
            ->where('active_time', 0)
            ->update([
                'active_time' => $this->data['active_time'],
                'update_time' => time()
            ]);
        $result2 = DB::table($tableAd)
            ->where('id', $device['ad_id'])
            ->where('active_time', 0)
            ->update([
                'active_status' => 1,
                'active_time' => $this->data['active_time'],
                'update_time' => time()
            ]);
        if (!$result1 || !$result2) {
            DB::rollBack();
            if ($this->attempts() < $this->tries) {
                $this->release(10 * $this->attempts());
            }
            echo json_encode(['r1' => $result1, 'r2' => $result2], 320) . PHP_EOL;
            return;
        }
        DB::commit();
        dispatch((new NotifyAdActiveJob([
            'app_code' => $this->data['app_code'],
            'device_id' => $this->data['device_id'],
            'source' => $device['source'],
            'ad_id' => $device['ad_id'],
            'active_time' => $this->data['active_time'],
        ]))->onQueue('{notify_ad_active}')->delay(10));

        echo 'NotifyAdActiveJob' . PHP_EOL;

        /*更新留存活跃表设备激活日期*/
        $day = date('Ymd', $this->data['active_time']);
        $tableActiveKeep = App::tableActiveKeep($this->data['app_code']);
        DB::table($tableActiveKeep)
            ->where('device_id', $this->data['device_id'])
            ->where('active_day', $day)
            ->update([
                'activate_day' => $day,
            ]);

        // 用户2小时广告统计 （使用新方法统计）
//        dispatch((new AdLevelJob([
//            'app_code' => $this->data['app_code'],
//            'device_id' => $this->data['device_id'],
//            'start_time' => $this->data['active_time'],
//            'end_time' => $this->data['active_time'], +(2 * 3600),
//        ]))->onQueue('ad_level')->delay(2 * 3600));

//        echo 'AdLevelJob' . PHP_EOL;

        // 关键行为
        $actionType = Account::actionRevenue($device['account_id'])['action_type'] ?? 0;
        if ($actionType > 0) {
            $data = [
                'app_code' => $this->data['app_code'],
                'device_id' => $this->data['device_id'],
                'source' => $device['source'],
                'account_id' => $device['account_id'],
                'ad_id' => $device['ad_id'],
                'plan_id' => $device['plan_id'],
                'start_time' => $this->data['active_time'],
                'end_time' => $this->data['active_time'] + (24 * 3600),
            ];

            // 队列
            $queueName = '{device_action' . mt_rand(0, 3) . '}';
            // 关键行为
            if ($actionType == 3) {
                dispatch((new DeviceActionJob($data))
                    ->onQueue($queueName)
                    ->delay(1800));
                echo 'DeviceActionJob' . PHP_EOL;
            } elseif ($actionType == 2) {
                // 加桌
                dispatch((new DeviceDesktopJob($data))
                    ->onQueue($queueName)
                    ->delay(1800));
                echo 'DeviceDesktopJob' . PHP_EOL;
            }
//            else {
//                dispatch((new AdRevenueJob($data))
//                    ->onQueue('{ad_revenue}')
//                    ->delay(1800));
//                echo 'AdRevenueJob' . PHP_EOL;
//            }
        }
    }
}
