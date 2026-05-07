<?php

declare(strict_types=1);

namespace App\Command\Temp;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class AdKeepEcpmActive extends HyperfCommand
{
    protected $name = 'temp:adKeepEcpmActive';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:回本率");
        /**
         * 麻雀、硬核打卡，12.1-12.8，新增激活的人均、ecpm、留存，分机型华为和OPPO的
         */
    }

    protected $appCodes = [
        'mqhz', 'yhdk'
    ];

    protected $vendor = ['oppo', 'realme'];

    protected $beginTime = '2022-12-01 00:00:00';
    protected $endTime = '2022-12-08 00:00:00';

    public function handle()
    {
        $this->info("开始".date("Y-m-d H:i:s"));
        $beginTime = strtotime($this->beginTime);
        for(; $beginTime<=strtotime($this->endTime);){
            foreach ($this->appCodes as $appCode){
                $this->info('doing:'.$appCode.':' . date("Y-m-d H:i:s", $beginTime));
                $avg0 = $this->avgShow($appCode, $beginTime, 0);
                $avg1 = $this->avgShow($appCode, $beginTime, 1);
                $avg2 = $this->avgShow($appCode, $beginTime, 2);
                $avg3 = $this->avgShow($appCode, $beginTime, 3);
                $avg4 = $this->avgShow($appCode, $beginTime, 4);
                $avg5 = $this->avgShow($appCode, $beginTime, 5);
                $avg6 = $this->avgShow($appCode, $beginTime, 6);
                $avg7 = $this->avgShow($appCode, $beginTime, 7);

                $active = $this->active($appCode, $beginTime);
                Db::connection('tools')->table('temp_20221209')->insert([
                    'date' => date('Y-m-d', $beginTime),
                    'appcode' => $appCode,
                    'vendor' => $this->vendor[0],
                    'avg_show_0' => $avg0['avgShow'],
                    'avg_show_1' => $avg1['avgShow'],
                    'avg_show_2' => $avg2['avgShow'],
                    'avg_show_3' => $avg3['avgShow'],
                    'avg_show_4' => $avg4['avgShow'],
                    'avg_show_5' => $avg5['avgShow'],
                    'avg_show_6' => $avg6['avgShow'],
                    'avg_show_7' => $avg7['avgShow'],

                    'ecpm_0' => $avg0['ecpm'],
                    'ecpm_1' => $avg1['ecpm'],
                    'ecpm_2' => $avg2['ecpm'],
                    'ecpm_3' => $avg3['ecpm'],
                    'ecpm_4' => $avg4['ecpm'],
                    'ecpm_5' => $avg5['ecpm'],
                    'ecpm_6' => $avg6['ecpm'],
                    'ecpm_7' => $avg7['ecpm'],

                    'liucun_1' => round($avg1['install'] / $active * 100, 2) . '%',
                    'liucun_2' => round($avg2['install'] / $active * 100, 2) . '%',
                    'liucun_3' => round($avg3['install'] / $active * 100, 2) . '%',
                    'liucun_4' => round($avg4['install'] / $active * 100, 2) . '%',
                    'liucun_5' => round($avg5['install'] / $active * 100, 2) . '%',
                    'liucun_6' => round($avg6['install'] / $active * 100, 2) . '%',
                    'liucun_7' => round($avg7['install'] / $active * 100, 2) . '%',
                ]);
            }
            $beginTime += 60 * 60 * 24;
        }
    }

    public function active($appCode, $beginTime)
    {
        $endTime = $beginTime + 60 * 60 * 24 - 1;
        $num = Db::connection('tools')->table("app_{$appCode} as app")
            ->where("app.active_time", ">=", $beginTime)
            ->where("app.active_time", "<=", $endTime)
            ->whereIn('app.vendor', $this->vendor)
            ->count();

        return $num;
    }

    public function avgShow($appCode, $beginTime, $day = 0)
    {
        $endTime = $beginTime + 24 * 60 * 60 - 1;
        $install = Db::connection('tools')->table("app_{$appCode}_active_keep as keep")
            ->where('keep.active_day', date("Ymd", $beginTime + 24 * 60 * 60 * $day))
            ->where('keep.activate_day', date("Ymd", $beginTime))
            ->leftJoin("app_{$appCode} as app", "app.android_id", "keep.device_id")
            ->whereIn('app.vendor', $this->vendor)
            ->count();
        $install += Db::connection('tools')->table("app_{$appCode}_active_keep_temp as keep")
            ->where('keep.active_day', date("Ymd", $beginTime + 24 * 60 * 60 * $day))
            ->where('keep.activate_day', date("Ymd", $beginTime))
            ->leftJoin("app_{$appCode} as app", "app.android_id", "keep.device_id")
            ->whereIn('app.vendor', $this->vendor)
            ->count();
        $incrementShow = Db::connection('tools')->table("app_{$appCode}_event_ad_show as show")
            ->whereBetween('show.create_time', [$beginTime + 24 * 60 * 60 * $day, $endTime + 24 * 60 * 60 * $day])
            ->leftJoin("app_{$appCode} as app", "app.android_id", "show.android_id")
            ->whereBetween('app.active_time', [$beginTime, $endTime])
            ->whereIn('app.vendor', $this->vendor)
            ->sum("show.times");
        $incrementShow += Db::connection('tools')->table("app_{$appCode}_event_ad_show_temp as show")
            ->whereBetween('show.create_time', [$beginTime + 24 * 60 * 60 * $day, $endTime + 24 * 60 * 60 * $day])
            ->leftJoin("app_{$appCode} as app", "app.android_id", "show.android_id")
            ->whereBetween('app.active_time', [$beginTime, $endTime])
            ->whereIn('app.vendor', $this->vendor)
            ->sum("show.times");

        $imputedRevenue = Db::connection('tools')->table("app_{$appCode}_event_interval_group as eig")
            ->where('eig.start_time', '>=', $beginTime + 24 * 60 * 60 * $day)
            ->where('eig.end_time', '<=', $endTime + 24 * 60 * 60 * $day)
            ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
            ->whereBetween('app.active_time', [$beginTime, $endTime])
            ->whereIn('app.vendor', $this->vendor)
            ->sum("eig.amount");
        $imputedRevenue += Db::connection('tools')->table("app_{$appCode}_event_interval_group_temp as eig")
            ->where('eig.start_time', '>=', $beginTime + 24 * 60 * 60 * $day)
            ->where('eig.end_time', '<=', $endTime + 24 * 60 * 60 * $day)
            ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
            ->whereBetween('app.active_time', [$beginTime, $endTime])
            ->whereIn('app.vendor', $this->vendor)
            ->sum("eig.amount");
        return [
            'avgShow' => round($install>0?$incrementShow /$install:0, 2),
            'ecpm' => round(($incrementShow > 0 ?$imputedRevenue / $incrementShow:0) * 1000, 2),
            'install' => $install,
        ];
    }
}