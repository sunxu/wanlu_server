<?php

declare(strict_types=1);

namespace App\Command\Temp;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class AdArpu extends HyperfCommand
{
    protected $name = 'temp:adArpu';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:回本率");
        /**
         *团团的，2.23-3.1，分日期新增用户的arpu和全屏的ipu
         */
    }

    protected $appCodes = [
        'ttgjx'
    ];

    protected $beginTime = '2023-02-23 00:00:00';
    protected $endTime = '2023-03-01 00:00:00';

    public function handle()
    {
        $this->info("开始".date("Y-m-d H:i:s"));
        $beginTime = strtotime($this->beginTime);
        for(; $beginTime<=strtotime($this->endTime);){
            foreach ($this->appCodes as $appCode){
                $this->info('doing:'.$appCode.':' . date("Y-m-d H:i:s", $beginTime));
                $arpu = Db::table('report_daily_launch_base')
                    ->where('created_date', date("Y-m-d", $beginTime))
                    ->where('app_code', $appCode)
                    ->first();
                $ipu = Db::connection('tools')->table($this->getadshowtable($beginTime, $appCode) . ' as show')
                    ->where('show.create_time', '>=', $beginTime)
                    ->where('show.create_time', '<', $beginTime + 60 * 60 * 24)
                    ->whereRaw("(show.info->'$.adType' = 'inter_full' or show.info->'$.adType' = 'full_video')")
                    ->leftJoin("app_{$appCode} as app", "app.android_id", "show.android_id")
                    ->where('app.create_time', '>=', $beginTime)
                    ->where('app.create_time', '<', $beginTime + 60 * 60 * 24)
                    ->sum('show.times');
                $arpu  = object_to_arr($arpu);
                $data = [
                    'date' => date("Y-m-d", $beginTime),
                    'appcode' => $appCode,
                    'arpu' => round(($arpu['increment_install'] > 0 ?$arpu['imputed_revenue'] / $arpu['increment_install']:0), 4),
                    'ipu' => $ipu
                ];
                Db::connection('tools')->table('temp_20230302')->insert($data);
            }
            $beginTime += 60 * 60 * 24;
        }
    }

    public function getadshowtable($time, $appCode)
    {
        if ($time < strtotime(date("Y-m-d 00:00:00", time() - 60 * 60 * 24 * 2))) {
            return "app_{$appCode}_event_ad_show_temp";
        }
        return "app_{$appCode}_event_ad_show";
    }
}