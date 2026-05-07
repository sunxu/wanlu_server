<?php

declare(strict_types=1);

namespace App\Command\Mkt;

use App\Service\Mkt\RuleService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;

/**
 * @Command
 */
class RulePlan extends HyperfCommand
{
    protected $name = 'mkt:rulePlan';

    public function handle()
    {
        make(RuleService::class)->handle();
    }
}
