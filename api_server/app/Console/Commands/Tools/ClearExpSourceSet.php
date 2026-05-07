<?php

namespace App\Console\Commands\Tools;

use App\Services\Common\App;
use App\Services\Common\EventAdDataBatchInsert;
use Illuminate\Console\Command;

class ClearExpSourceSet extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'clear-exp-source-set';

    /**
     * Command description
     * @var string
     */
    protected $description = '清除过期的有序集合值';

    /**
     * Command Handle
     */
    public function handle()
    {
        // 工具
        $apps = App::codeList([data_get(App::$appType, 'tool')]);
        foreach ($apps as $code => $name) {
            (new EventAdDataBatchInsert())->clearExp($code);
        }
    }
}
