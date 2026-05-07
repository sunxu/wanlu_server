<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Service\Cash\DeviceService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class ScheduleDevice extends HyperfCommand
{

    protected $name = 'temp:schedule_device';
    protected $adService;

    protected function configure()
    {
        parent::configure();
        $this->setDescription("设备数");
    }

    public function handle()
    {
        $DeviceService = make(DeviceService::class);
        $DeviceService->scheduleDevice("2026-01-24");
    }

}