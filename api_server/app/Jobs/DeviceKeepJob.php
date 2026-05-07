<?php
/**
 * 设备留存
 */

namespace App\Jobs;

use App\Services\Common\Account;
use App\Services\Common\App;
use DB;

class DeviceKeepJob extends BaseJob
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
        $deviceInfo = DB::table(App::tableDevice($this->data['app_code']))
            ->where('android_id', $this->data['device_id'])
            ->first();
        if (empty($deviceInfo)
            || empty($deviceInfo['source'])
            || empty($deviceInfo['account_id'])
            || empty($deviceInfo['ad_id'])
            || empty($deviceInfo['active_time'])
            || $deviceInfo['keep_time'] > 0
            // 次留-自然日
            || (
                !Account::keep24($deviceInfo['account_id'])
                && date('Ymd', $deviceInfo['active_time']) != date('Ymd', $this->data['keep_time'] - 86400)
            )
            // 24-48小时次留
            || (
                Account::keep24($deviceInfo['account_id'])
                && (
                    $this->data['keep_time'] - $deviceInfo['active_time'] < 86400
                    || $this->data['keep_time'] - $deviceInfo['active_time'] > 172800
                )
            )
        ) {
            return;
        }

        $currTime = time();
        DB::beginTransaction();
        $result1 = DB::table(App::tableAdBySource($this->data['app_code'], $deviceInfo['source']))
            ->where('id', '=', $deviceInfo['ad_id'])
            ->where('active_time', '>', 0)
            ->where('keep_time', '=', 0)
            ->update([
                'keep_status' => 1,
                'keep_time' => $this->data['keep_time'],
                'update_time' => $currTime
            ]);
        $result2 = DB::table(App::tableDevice($this->data['app_code']))
            ->where('id', '=', $deviceInfo['id'])
            ->where('active_time', '>', 0)
            ->where('keep_time', 0)
            ->update([
                'keep_time' => $this->data['keep_time'],
                'update_time' => $currTime
            ]);
        if ($result1 && $result2) {
            DB::commit();
            dispatch((new NotifyAdKeepJob([
                'app_code' => $this->data['app_code'],
                'device_id' => $this->data['device_id'],
                'keep_time' => $this->data['keep_time'],
            ]))->onQueue('{notify_ad_keep}'));
        } else {
            DB::rollBack();
        }
    }
}
