<?php
/**
 * 回收-小时
 */

namespace App\Console\Commands\Revenue;

use App\Jobs\Revenue\RevenueHourJob;
use App\Jobs\Revenue\SumHourJob;
use App\Services\Common\App;
use Illuminate\Console\Command;

class RevenueHour extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'revenue-hour {hour} {appcode?*}';

    /**
     * Command description
     * @var string
     */
    protected $description = '回收-小时';

    /**
     * Command Handle
     */
    public function handle()
    {
        $arguments = $this->arguments();
        // 默认前一小时
        $hour = date('Y-m-d H:00:00', strtotime(empty($arguments['hour']) ? '-1 hour' : $arguments['hour']));
        // 工具-自投
        $apps = App::codeList([data_get(App::$appType, 'tool')], $arguments['appcode'], [data_get(App::$adStatus, 'self')]);
        foreach ($apps as $code => $name) {
//            dispatch((new RevenueHourJob([
//                'app_code' => $code,
//                'hour' => $hour,
//            ])));

            dispatch((new SumHourJob([
                'app_code' => $code,
                'hour' => $hour,
            ]))->onQueue('{crontab}'));
        }
        echo json_encode($apps, 320) . PHP_EOL;
    }
}
