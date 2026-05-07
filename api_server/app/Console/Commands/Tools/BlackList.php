<?php
// 新建应用
namespace App\Console\Commands\Tools;

use App\Services\Common\Device;
use Illuminate\Console\Command;

class BlackList extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'blacklist-init';

    /**
     * Command description
     * @var string
     */
    protected $description = 'php artisan blacklist-init';

    /**
     * Command Handle
     */
    public function handle()
    {
        ini_set('memory_limit', '-1');
        (new Device())->initBlack();
    }
}
