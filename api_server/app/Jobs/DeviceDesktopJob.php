<?php
/**
 * 关键行为
 */

namespace App\Jobs;

use App\Services\Common\Account;
use App\Services\Common\App;
use DB;

class DeviceDesktopJob extends BaseJob
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
        // 关键行为时间
        $actionTime = DB::table(App::tableDevice($this->data['app_code']))
            ->where('android_id', '=', $this->data['device_id'])
            ->value('action_time');
        if ($actionTime > 0) {
            return;
        }

        // 关键行为条件
        $actionValue = Account::actionValue($this->data['account_id']);

        if ($actionValue['action_type'] != 2) {
            return;
        }

        $id = DB::table(App::tableEventDialog($this->data['app_code']))
            ->where('android_id', $this->data['device_id'])
            ->where('key_type', 'desktop')
            ->where('req_type', 'yes')
            ->value('id');
        if ($id) {
            return $this->action();
        }
        
        if ($this->attempts() < $this->tries && time() < $this->data['end_time']) {
            $this->release($this->minutes * 60);
        }
    }


    /**
     * 关键行为达成
     */
    private function action()
    {
        $currTime = time();
        // 满足关键行为
        $tableAd = App::tableAdBySource($this->data['app_code'], $this->data['source']);
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
        dispatch((new NotifyAdDesktopJob([
            'app_code' => $this->data['app_code'],
            'device_id' => $this->data['device_id'],
            'account_id' => $this->data['account_id'],
            'source' => $this->data['source'],
            'ad_id' => $this->data['ad_id'],
            'action_time' => $currTime,
            'active_time' => $this->data['start_time'],
        ]))->onQueue('{notify_ad_action}'));
    }
}
