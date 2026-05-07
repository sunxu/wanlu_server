<?php

namespace App\Console\Commands\Event;

use App\Jobs\ComJob;
use App\Services\Common\App;
use App\Services\Common\Event;
use Illuminate\Console\Command;

class Insert extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'event-insert';

    /**
     * Command description
     * @var string
     */
    protected $description = '信息打点批量入库';

    protected $eventTypes = [
        'active' => ['active'],
        'dialog' => ['dialog'],
        'strategy' => ['strategy'],
        'ad' => ['ad_request', 'ad_full', 'ad_show', 'ad_click', 'ad_close'],
    ];

    /**
     * Command Handle
     */
    public function handle()
    {
        // 工具
        $apps = App::codeList([data_get(App::$appType, 'tool')]);
        $class = new Event();
        foreach ($apps as $appcode => $appname) {
            foreach ($this->eventTypes as $func => $types) {
                foreach ($types as $type) {
                    dispatch((new ComJob([
                        'class' => $class,
                        'func' => $func,
                        'args' => [$appcode, $type]
                    ]))->onQueue('{crontab}'));
                }
            }
        }
    }
}
