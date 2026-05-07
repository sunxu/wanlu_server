<?php

declare(strict_types=1);

namespace App\Command\Cash;

use App\Service\Cash\RevenueService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use function make;

/**
 * @Command
 */
class MiddleRevenueCommand extends HyperfCommand
{
    protected $name = 'middle:revenue';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("中间表：回收");
    }

    public function handle()
    {
        $this->info('Begin:' . date("Y-m-d H:i:s", time()));
        try {
            make(RevenueService::class)->scheduleRevenueYesterday();
        }catch (\Exception $e){
            var_dump(['message'=>$e->getMessage(), 'file'=>$e->getFile()]);
        }
        $this->info("End:" . date("Y-m-d H:i:s", time()));
    }
}