<?php

declare(strict_types=1);

namespace App\Command\Mkt;

use App\Model\App;
use App\Service\Mkt\HonorService;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class Honor extends HyperfCommand
{
    protected $name = 'mkt:honor';

    protected function getArguments()
    {
        return [
            ['date', InputArgument::OPTIONAL, '日期', date('Y-m-d', strtotime('-1 days'))],
            ['code', InputArgument::OPTIONAL, '应用', ''],
        ];
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {
        $args = $this->input->getArguments();
        $date = data_get($args, 'date');
        $code = data_get($args, 'code');

        $appList = (new App())->appList([], $code ? [$code] : []);

        $dateArr = [
            'today' => date('Y-m-d'),
            'yesterday' => date('Y-m-d', strtotime('-1 days')),
        ];
        $date = $dateArr[$date] ?? $date;
        foreach ($appList as $appCode => $appName) {
            go(function () use ($appCode, $date) {
                make(HonorService::class)->handle($appCode, (string)$date);
            });
        }
    }
}
