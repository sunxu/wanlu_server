<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Service\Paid\PaidService;
use App\Service\Placement\LaunchBaseService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;

/**
 * @Command
 */
class HistoryLaunchBase extends HyperfCommand
{
    protected $name = 'temp:historyLaunchBase';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:统计历史数据");
    }

    public function handle()
    {
        $this->info('Begin');
//        $time = strtotime("yesterday");
//        for (; $time >= strtotime('-2 days');) {
//            $this->info(date("Y-m-d", $time));
//            try {
//                make(LaunchBaseService::class)->dailyStore(date("Y-m-d", $time));
//            } catch (\Exception $exception) {
//
//            }
//            $time -= 60 * 60 * 24;
//        }
        make(LaunchBaseService::class)->sendReport();
        $this->info("End");
    }
}