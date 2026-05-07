<?php
/**
 * 回本率统计
 */

namespace App\Console\Commands\Statistics;

use App\Jobs\LocalRecoveryJob;
use App\Services\Common\App;
use Illuminate\Console\Command;

class LocalRecovery extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'local-recovery {date?} {appcode?*}';

    /**
     * Command description
     * @var string
     */
    protected $description = '回本率数据统计';

    /**
     * Command Handle
     */
    public function handle()
    {
        $arguments = $this->arguments();

        $day = $arguments['date'] ?? date('Y-m-d', strtotime('-1 day'));

        // 工具-自投
        $apps = App::codeList([data_get(App::$appType, 'tool')], $arguments['appcode'] ?? [], [data_get(App::$adStatus, 'self')]);

        foreach ($apps as $appcode => $appname) {
            $job_params = [];
            $job_params['appcode'] = $appcode;
            $job_params['day'] = $day;
            dispatch(new LocalRecoveryJob($job_params));
        }
    }
}
