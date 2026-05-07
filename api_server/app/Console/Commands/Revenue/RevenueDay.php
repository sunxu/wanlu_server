<?php
/**
 * 回收-天
 */

namespace App\Console\Commands\Revenue;

use App\Jobs\Revenue\RevenueDayJob;
use App\Services\Common\App;
use Illuminate\Console\Command;

class RevenueDay extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'revenue-day {date} {appcode?*}';

    /**
     * Command description
     * @var string
     */
    protected $description = '回收-天';

    /**
     * Command Handle
     */
    public function handle()
    {
        $arguments = $this->arguments();
        // 默认当日
        $date = date('Y-m-d', empty($arguments['date']) ? time() : strtotime($arguments['date']));

        // 工具-自投
        $apps = App::codeList([data_get(App::$appType, 'tool')], $arguments['appcode'], [data_get(App::$adStatus, 'self')]);
        foreach ($apps as $code => $name) {
            dispatch((new RevenueDayJob([
                'app_code' => $code,
                'date' => $date,
            ])));
        }
        echo json_encode($apps, 320) . PHP_EOL;
    }
}
