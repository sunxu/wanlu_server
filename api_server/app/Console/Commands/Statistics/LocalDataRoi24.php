<?php
/**
 * ROI24
 */

namespace App\Console\Commands\Statistics;

use App\Jobs\Roi24Job;
use App\Services\Common\App;
use Illuminate\Console\Command;

class LocalDataRoi24 extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'local-data-roi24';

    /**
     * Command description
     * @var string
     */
    protected $description = 'ROI24';

    /**
     * Command Handle
     */
    public function handle()
    {
        $apps = App::codeList([data_get(App::$appType, 'tool')], [], [data_get(App::$adStatus, 'self')]);

        foreach ($apps as $appcode => $appname) {

            /*计算24小时ROI*/
            $job_params = [];
            $job_params['appcode'] = $appcode;
            $job_params['day'] = date('Y-m-d');//当天
            $job_params['type'] = 'install';//安装
            dispatch(new Roi24Job($job_params))->onQueue('{roi24}');

            $job_params = [];
            $job_params['appcode'] = $appcode;
            $job_params['day'] = date('Y-m-d', strtotime('-1 day'));//昨天
            $job_params['type'] = 'install';//安装
            dispatch(new Roi24Job($job_params))->onQueue('{roi24}');

            $job_params = [];
            $job_params['appcode'] = $appcode;
            $job_params['day'] = date('Y-m-d');//当天
            $job_params['type'] = 'activate';//激活
            dispatch(new Roi24Job($job_params))->onQueue('{roi24}');

            $job_params = [];
            $job_params['appcode'] = $appcode;
            $job_params['day'] = date('Y-m-d', strtotime('-1 day'));//昨天
            $job_params['type'] = 'activate';//激活
            dispatch(new Roi24Job($job_params))->onQueue('{roi24}');

        }
    }
}
