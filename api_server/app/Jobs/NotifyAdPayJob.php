<?php
/**
 * 广告投放支付行为通知
 */

namespace App\Jobs;

use App\Services\Common\App;
use App\Services\Mkt\MktService;
use DB;

class NotifyAdPayJob extends BaseJob
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

        $tableDevice = App::tableDevice($this->data['app_code']);
        $tableTrade = App::tableTrade($this->data['app_code']);

        $tradeInfo = DB::table("{$tableTrade}")
            ->where('trade_no', $this->data['trade_no'])
            ->where('trade_status', 1)
            ->first();

        $deviceInfo = DB::table($tableDevice)
            ->where('android_id', $tradeInfo['account'])
            ->first();

        if ((int)$deviceInfo['source'] === \App\Constants\AdConst::PUT_SOURCE_MAP[\App\Constants\AdConst::PUT_SOURCE_TX]) {
            return; // 首期腾讯仅接入激活、留存和关键行为回传。
        }

        DB::table($tableDevice)
            ->where('id', $deviceInfo['id'])
            ->update([
                'action_time' => $tradeInfo['created_time'],
                'update_time' => time()
            ]);

        if (empty($deviceInfo['ad_id'])) {
            return;
        }

        $tableAd = App::tableAdBySource($this->data['app_code'], $deviceInfo['source']);
        $ad = DB::table($tableAd)
            ->where('id', $deviceInfo['ad_id'])
            ->first();
        if (empty($ad) || $ad['active_status'] != 2) {
            echo 'active_status != 2' . PHP_EOL;
            return;
        }

        $callback = (new MktService($deviceInfo['source']))->callback('pay', $tradeInfo['created_time'], (array)$ad);

        if ($callback) {
            $update = [
                'update_time' => time(),
                'action_time' => $tradeInfo['created_time'],
                'action_status' => 2,
                'action_times' => DB::raw('action_times + 1')
            ];
            DB::table($tableAd)
                ->where('id', $ad['id'])
                ->update($update);
        } else if ($this->attempts() < $this->tries) {
            $this->release(10 * $this->attempts());
        }
    }
}
