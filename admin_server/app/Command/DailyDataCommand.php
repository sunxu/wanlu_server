<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Placement\LaunchBaseService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;

/**
 * @Command
 */
#[Command]
class DailyDataCommand extends HyperfCommand
{
    protected $name = 'daily:data';

    public function configure()
    {
        parent::configure();
        $this->setDescription('日报统计');
    }

    public function handle()
    {
        make(LaunchBaseService::class)->schedule();
    }
}
