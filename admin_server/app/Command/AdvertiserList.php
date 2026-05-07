<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Mkt\MktService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;

/**
 * @Command
 */
#[Command]
class AdvertiserList extends HyperfCommand
{
    protected $name = 'temp:advertiserList';

    public function configure()
    {
        parent::configure();
        $this->setDescription('AdvertiserList');
    }

    public function handle()
    {
        make(MktService::class)->advertiserList(1859171945424010);
    }
}
