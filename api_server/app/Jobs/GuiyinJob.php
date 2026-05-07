<?php
/**
 * 异步归因
 */

namespace App\Jobs;

use App\Services\Common\App;
use App\Services\Mkt\GuiyinService;
use DB;
use Exception;

class GuiyinJob extends BaseJob
{
    /**
     * 重试时间间隔
     * @var array
     */
    protected $triesTimes = [
        1 => 2 * 60,
        2 => 5 * 60,
        3 => 10 * 60,
        4 => 30 * 60,
        5 => 60 * 60,
    ];

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        if (empty($this->data['app_code']) || empty($this->data['android_id'])) {
            return;
        }

        if ((App::getAppByCode($this->data['app_code'])['ad_status'] ?? 0) == App::$adStatus['agency']) {
            return;
        }

        $tableDevice = App::tableDevice($this->data['app_code']);

        $device = DB::table($tableDevice)
            ->where('android_id', $this->data['android_id'])
            ->first();

        if (empty($device) || $device['ad_time'] > 0) {
            return;
        }

        if (GuiyinService::handle($this->data['app_code'], (array)$device)) {
            echo 'GuiyinService::handle success. data:' . json_encode($this->data) . PHP_EOL;
            return;
        }

        if ($this->attempts() < $this->tries) {
            $this->release($this->triesTimes[$this->attempts()]);
        }
    }
}
