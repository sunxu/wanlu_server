<?php

declare(strict_types=1);

namespace App\Command\Cash;

use App\Service\Cash\DeviceService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\DbConnection\Db;
use function make;

/**
 * @Command
 */
class MiddleDeviceCommand extends HyperfCommand
{
    protected $name = 'middle:device';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("中间表：设备");
    }

    public function handle()
    {
        $this->info('Begin:' . date("Y-m-d H:i:s", time()));
        try {
            make(DeviceService::class)->scheduleDeviceToday();
        } catch (\Exception $e) {
            var_dump(['message' => $e->getMessage(), 'file' => $e->getFile()]);
        }
        $this->info("End:" . date("Y-m-d H:i:s", time()));
    }
}