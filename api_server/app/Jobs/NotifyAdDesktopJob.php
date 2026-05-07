<?php
/**
 * 广告投放加桌通知
 */

namespace App\Jobs;

use App\Services\Common\Account;
use App\Services\Common\App;
use App\Services\Helper\Helper;
use App\Services\Mkt\MktService;
use DB;
use Log;

class NotifyAdDesktopJob extends BaseJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        echo json_encode($this->data, 320) . PHP_EOL;

        $this->service();
    }


    protected function service()
    {
        $table = App::tableAdBySource($this->data['app_code'], $this->data['source']);
        // 激活回传
        $ad = DB::table($table)
            ->where('id', $this->data['ad_id'])
            ->first();

        if (empty($ad) || $ad['active_status'] != 2 || $ad['action_status'] != 1) {
            echo 'active_status' . PHP_EOL;
            return;
        }
        $ad['app_code'] = $this->data['app_code'];
        try {
            $result = (new MktService($this->data['source']))->callback('add_desktop', $ad['action_time'], (array)$ad);
        } catch (\Exception $e) {
            $result = false;
        }

        $result ? $this->notifySuccess($table, $ad['id']) : $this->notifyFail($table, $ad['id']);
    }

    /**
     * 成功
     * @param $table
     * @param $id
     */
    protected function notifySuccess($table, $id)
    {
        DB::table($table)
            ->where('id', $id)
            ->where("action_status", 1)
            ->update([
                'update_time' => time(),
                "action_status" => 2
            ]);
        echo 'success' . PHP_EOL;
    }

    /**
     * 失败
     * @param $table
     * @param $id
     */
    protected function notifyFail($table, $id)
    {
        DB::table($table)
            ->where('id', $id)
            ->where("action_status", 1)
            ->update([
                'update_time' => time(),
                "action_times" => DB::raw("action_times + 1")
            ]);
        if ($this->attempts() < $this->tries) {
            $this->release(10 * $this->attempts());
        }
        echo 'fail' . PHP_EOL;
    }
}
