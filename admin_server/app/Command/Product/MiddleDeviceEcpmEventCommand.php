<?php

declare(strict_types=1);

namespace App\Command\Product;

use App\Service\Product\ProductService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use function make;

/**
 * @Command
 */
class MiddleDeviceEcpmEventCommand extends HyperfCommand
{
    protected $name = 'middle:deviceecpmevent';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("中间表：用户ecpm行为分布");
    }

    public function handle()
    {
        $this->info('Begin:' . date("Y-m-d H:i:s", time()));
        try {
            make(ProductService::class)->scheduleDeviceEcpmEvent(1);
//            make(ProductService::class)->scheduleDeviceEcpmEvent(2);
//            make(ProductService::class)->scheduleDeviceEcpmEvent(3);
        } catch (\Exception $e) {
            var_dump(['message' => $e->getMessage(), 'file' => $e->getFile()]);
        }
        $this->info("End:" . date("Y-m-d H:i:s", time()));
    }
}