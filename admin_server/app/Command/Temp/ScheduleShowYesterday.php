<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Service\Mkt\MktService;
use App\ModelTools\App;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use App\Resource\Launch\LaunchAd\Advertiser;
use App\Resource\Launch\LaunchAd\Ad;
use App\Service\Launch\AdService;
use App\Service\Cash\ShowService;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class ScheduleShowYesterday extends HyperfCommand
{

    protected $name = 'temp:schedule_show_yesterday';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:ScheduleShowYesterday");
    }

    public function handle()
    {
        $this->line('Hello Hyperf!', 'ScheduleShowYesterday');
        $showService = make(ShowService::class);
        #$showService->scheduleShowYesterday();
        $showService->scheduleShow('2025-07-10');
        $this->line('Hello Hyperf!', 'ScheduleShowYesterday success');
    }
}
