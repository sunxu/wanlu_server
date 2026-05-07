<?php
/**
 * 广告打点
 */

namespace App\Console\Commands\Event;

use App\Services\Common\App;
use App\Services\Common\EventAdDataBatchInsert;
use Illuminate\Console\Command;

class EventAd extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'event-ad';

    /**
     * Command description
     * @var string
     */
    protected $description = '广告打点批量入库';

    /**
     * Command Handle
     */
    public function handle()
    {
        // 工具
        $apps = App::codeList([data_get(App::$appType, 'tool')], [], [data_get(App::$adStatus, 'self')]);
        foreach ($apps as $appcode => $appname) {
            (new EventAdDataBatchInsert())->insert($appcode);
        }
    }
}
