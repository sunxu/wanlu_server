<?php

declare(strict_types=1);

namespace App\Command\Cash;

use App\Service\Cash\EcpmService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\DbConnection\Db;
use function make;

/**
 * @Command
 */
class MiddleEcpmCommand extends HyperfCommand
{
    protected $name = 'middle:ecpm';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("中间表：代码位ecpm");
    }

    public function handle()
    {
        $this->info('Begin:' . date("Y-m-d H:i:s", time()));
        try {
            make(EcpmService::class)->scheduleEcpmYesterday();
            $appCode = 'ttgjx';
            $doDate = '2023-04-11';
            $day = 1;
        }catch (\Exception $e){
            var_dump(['message'=>$e->getMessage(), 'file'=>$e->getFile()]);
        }
        $this->info("End:" . date("Y-m-d H:i:s", time()));
    }
}