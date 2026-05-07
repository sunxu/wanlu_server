<?php

declare(strict_types=1);

namespace App\Command\Mkt;

use App\Service\Mkt\AutoAppService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use function make;

/**
 * @Command
 */
class AutoApp extends HyperfCommand
{
    protected $name = 'mkt:autoApp';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("检测计划");
    }

    public function handle()
    {
        $this->info('Begin:' . date("Y-m-d H:i:s", time()));
        try {
            make(AutoAppService::class)->schedulePlan();
        } catch (\Exception $e) {
            var_dump(['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        }
        $this->info("End:" . date("Y-m-d H:i:s", time()));
    }
}