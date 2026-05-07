<?php

declare(strict_types=1);

namespace App\Command\Cash;

use App\Service\Cash\LiveService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use function make;

/**
 * @Command
 */
class MiddleLiveCommand extends HyperfCommand
{
    protected $name = 'middle:live';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("中间表：心跳数据");
    }

    public function handle()
    {
        $this->info('Begin:' . date("Y-m-d H:i:s", time()));
        try {
            make(LiveService::class)->scheduleLiveToday('2022-11-30');
        }catch (\Exception $e){
            var_dump(['message'=>$e->getMessage(), 'file'=>$e->getFile()]);
        }
        $this->info("End:" . date("Y-m-d H:i:s", time()));
    }
}