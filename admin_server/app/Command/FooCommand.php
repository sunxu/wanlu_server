<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
#[Command]
class FooCommand extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct('foo');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Hyperf Demo Command');
    }

    protected function getArguments()
    {
        return [
            ['date', InputArgument::OPTIONAL, '日期']
        ];
    }

    public function handle()
    {
        $date = $this->input->getArgument('date');
        $date = $date ?? date('Y-m-d');
        $this->ToolsDeviceAppList($date);
    }

    /*工具项目设备应用列表处理*/
    public function ToolsDeviceAppList($date)
    {
        $start_time = strtotime($date);
        $end_time = $start_time + 86400;
        $max_day_times = 10;

        Db::connection('tools')->table('app_list')
            ->whereBetween('create_time', [$start_time, $end_time])
            ->groupBy(['device_id'])
//            ->limit(1)
            ->get()->map(function ($item) use ($max_day_times, $start_time, $date) {
                $day_times_list = [];
                for ($i = 0; $i <= $max_day_times; $i++) {
                    $day = date('Y-m-d', $start_time - (86400 * $i));
                    $day_times_list[$day] = ['day_times' => $i, 'app_count' => 0];
                }

                $device_id = $item->device_id;
                $app_list = json_decode($item->app_list, true);
                foreach ($app_list as $app => $time) {
                    $time = floor($time / 1000);
                    $day = date('Y-m-d', (int)$time);
                    if (isset($day_times_list[$day])) {
                        $day_times_list[$day]['app_count'] += 1;
                    }
                }

                foreach ($day_times_list as $row) {
                    $id = Db::table('device_app_list')->insertGetId([
                        'date' => $date,
                        'app_code' => $item->app_code,
                        'device_id' => $device_id,
                        'day_times' => $row['day_times'],
                        'app_count' => $row['app_count'],
                    ]);

                    echo '插入成功：' . $id .PHP_EOL;
                }

            });
    }
}
