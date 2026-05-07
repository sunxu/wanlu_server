<?php
/**
 * 广告数据|广告打点数据间隔统计入库
 */

namespace App\Console\Commands\Statistics;

use App\Jobs\LevelTotalJob;
use App\Jobs\Roi24Job;
use App\Services\Common\App;
use App\Services\Common\EventAdDataBatchInsert;
use App\Services\Common\EventAdDataIntervalGroup;
use Illuminate\Console\Command;

class LocalAdIntervalGroup extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'local-ad-interval-group';

    /**
     * Command description
     * @var string
     */
    protected $description = '广告打点数据间隔统计入库';

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
