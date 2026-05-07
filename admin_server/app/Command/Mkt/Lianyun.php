<?php

declare(strict_types=1);

namespace App\Command\Mkt;

use App\Service\Lianyun\DataService;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class Lianyun extends HyperfCommand
{
    protected $name = 'mkt:lianyun';

    protected function getArguments()
    {
        return [
            ['date', InputArgument::OPTIONAL, '日期', date('Y-m-d', strtotime('-1 days'))]
        ];
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {
        $args = $this->input->getArguments();
        $date = data_get($args, 'date');

        $dateArr = [
            'today' => date('Y-m-d'),
            'yesterday' => date('Y-m-d', strtotime('-1 days')),
        ];
        $date = $dateArr[$date] ?? $date;
        make(DataService::class)->handle((string)$date);
    }
}
