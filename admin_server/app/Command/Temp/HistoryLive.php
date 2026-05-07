<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Service\Cash\DeviceService;
use App\Service\Cash\LiveService;
use App\Service\Cash\RevenueService;
use App\Service\Cash\ShowService;
use App\Service\Placement\LaunchBaseService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

/**
 * @Command
 */
class HistoryLive extends HyperfCommand
{
    protected $name = 'temp:historylive';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:统计历史数据");
    }

    public function handle()
    {
        $this->info('Begin');
        for ($date = date('Y-m-d', strtotime('-7 days')); strtotime($date) < strtotime(date('Y-m-d')); $date = date('Y-m-d', strtotime('+1 days', strtotime($date)))) {
            $this->info($date);
            make(DeviceService::class)->scheduleDevice($date);
            make(ShowService::class)->scheduleShow($date);
        }
        $this->info("End");
    }
}