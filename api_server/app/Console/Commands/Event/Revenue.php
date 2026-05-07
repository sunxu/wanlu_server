<?php

namespace App\Console\Commands\Event;

use App\Jobs\ComJob;
use App\Services\Common\App;
use App\Services\Common\Event;
use Illuminate\Console\Command;

class Revenue extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'event-revenue';

    /**
     * Command description
     * @var string
     */
    protected $description = '回收入库';

    /**
     * Command Handle
     */
    public function handle()
    {
        // 工具
        $apps = App::codeList([data_get(App::$appType, 'tool')]);
        $class = new Event();
        $time = time() - $class->intervalTime;
        foreach ($apps as $appcode => $appname) {
            dispatch(new ComJob([
                'class' => $class,
                'func' => 'insertRevenue',
                'args' => [$appcode, $time]
            ]))->onQueue('{crontab}');
        }
    }
}
