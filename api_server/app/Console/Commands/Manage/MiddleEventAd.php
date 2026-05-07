<?php
/**
 * 中间表
 * Created by PhpStorm.
 */

namespace App\Console\Commands\Manage;

use App\Jobs\MiddleEventJob;
use App\Services\Common\App;
use App\Services\Common\EventAdDataBatchInsert;
use Illuminate\Console\Command;

class MiddleEventAd extends Command
{
    /**
     * @var string Command Name
     */
    protected $signature = "middle-event-ad {date} {appcode?*}";

    /**
     * Command description
     * @var string
     */
    protected $description = "广告打点中间表数据";

    /**
     * Command Handle
     */

    public function handle()
    {
        $arguments = $this->arguments();
        // 工具
        $apps = App::codeList([data_get(App::$appType, 'tool')], $arguments['appcode'], [data_get(App::$adStatus, 'self'), data_get(App::$adStatus, 'agency')]);
        foreach ($apps as $code => $name) {
            $parames = [];
            $parames['date'] = $arguments['date'];
            $parames['appcode'] = $code;
            dispatch(new MiddleEventJob($parames))->onQueue('{crontab}');
        }
    }


}
