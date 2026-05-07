<?php
/**
 * 用户分层统计
 */

namespace App\Console\Commands\Statistics;

use App\Jobs\LevelTotalJob;
use App\Services\Common\EventAdDataBatchInsert;
use Illuminate\Console\Command;

class LocalAdLevel extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'local-ad-level';

    /**
     * Command description
     * @var string
     */
    protected $description = '分层用户统计';


    /**
     * Command Handle
     */
    public function handle()
    {
        $appcodes = (new EventAdDataBatchInsert())->getAppcode();
        foreach ($appcodes as $appcode) {
            /*分层统计广告展示层级*/
            $job_params = [];
            $job_params['appcode'] = $appcode;
            $job_params['day_time'] = strtotime('-1 day');//前一天的时间戳
            dispatch(new LevelTotalJob($job_params))->onQueue('{levelTotal}');
        }
    }
}
