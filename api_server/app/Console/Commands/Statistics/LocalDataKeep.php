<?php
/**
 * 投放长留存数据
 * N 天前的新增截止昨日的留存
 */

namespace App\Console\Commands\Statistics;

use App\Jobs\LocalDataKeepJob;
use App\Services\Common\App;
use Illuminate\Console\Command;

class LocalDataKeep extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'local-data-keep {date?} {appcode?*}';

    /**
     * Command description
     * @var string
     */
    protected $description = '投放长留存数据';

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
            /*安装留存*/
            $job_params = [];
            $job_params['appcode'] = $appcode;
            $job_params['day'] = $day;
            $job_params['type'] = 'install';
            dispatch(new LocalDataKeepJob($job_params));

            /*激活留存*/
            $job_params = [];
            $job_params['appcode'] = $appcode;
            $job_params['day'] = $day;
            $job_params['type'] = 'activate';
            dispatch(new LocalDataKeepJob($job_params));
        }
    }
}
