<?php

declare(strict_types=1);

namespace App\Command\Temp;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class AdRevenue extends HyperfCommand
{
    protected $name = 'temp:adrevenue';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:回本率");
        /**
         * 开屏-first这个广告覆盖的“新增用户量”占每日新增用户量的占比
         */
    }

    protected $appCodes = [
        'bmhz'
    ];

    protected $beginTime = '2023-02-21 00:00:00';
    protected $endTime = '2023-02-21 00:00:00';

    public function handle()
    {
        $this->info("开始" . date("Y-m-d H:i:s"));
        $currentTime = strtotime('2023-02-21 00:00:00');
        for(; $currentTime < strtotime('2023-02-22 00:00:00');){
            $this->info("Doing" . date("Y-m-d H:i:s", $currentTime));
            $infos = Db::connection('tools')->table("app_bmhz_event_interval_group")
                ->where('start_time', $currentTime)
                ->select('info')->get()->all();
            $infos = object_to_arr($infos);
            foreach ($infos as $value){
                $info = json_decode(data_get($value, 'info', ''), true);
                $data = [];
                foreach ($info as $item){
                    $price = data_get($item, 'price', 0);
                    $times = data_get($item, 'times');
                    $placement_id = data_get($item, 'placement_id');
                    $data[] = [
                        'placement_id' => $placement_id,
                        'price' => $price,
                        'times' => $times
                    ];
                }
                Db::connection('tools')->table('temp_20230222')->insert($data);
            }

            $currentTime = 10 * 60 + $currentTime;
        }
        $this->info("结束" . date("Y-m-d H:i:s"));
    }

}