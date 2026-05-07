<?php

declare(strict_types=1);

namespace App\Command\Temp;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class AdKeepEcpm extends HyperfCommand
{
    protected $name = 'temp:adKeepEcpm';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:回本率");
        /**
         * 玲珑的，9.23-9.30只针对华为机型，10.22-10.30只针对OPPO机型
         *
         * 睿知、玲珑、周到，只针对小米机型
        数据说明：比如，10.22新增了100个OPPO用户，这些用户当天ecpm和人均展示，然后到第二天留下来30个用户，这30个用户第二天的ecpm和人均展示
         */
    }

    protected $appCodes = [
        'llwbx'
    ];

    protected $beginTime = '2022-09-23 00:00:00';
    protected $endTime = '2022-09-30 00:00:00';

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

                $install = $this->install($appCode, $beginTime);
                Db::connection('tools')->table('temp_20221115')->insert([
                    'date' => date('Y-m-d', $beginTime),
                    'appcode' => $appCode,

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

                    'liucun_1' => round($avg1['install'] / $install * 100, 2) . '%',
                    'liucun_2' => round($avg2['install'] / $install * 100, 2) . '%',
                    'liucun_3' => round($avg3['install'] / $install * 100, 2) . '%',
                    'liucun_4' => round($avg4['install'] / $install * 100, 2) . '%',
                    'liucun_5' => round($avg5['install'] / $install * 100, 2) . '%',
                    'liucun_6' => round($avg6['install'] / $install * 100, 2) . '%',
                    'liucun_7' => round($avg7['install'] / $install * 100, 2) . '%',
                    ]);
            }
            $beginTime += 60 * 60 * 24;
        }
    }

    public function install($appCode, $beginTime)
    {
        $endTime = $beginTime + 60 * 60 * 24 - 1;
        return Db::connection('tools')->table("app_{$appCode}_temp as app")
            ->where("app.create_time", ">=", $beginTime)
            ->where("app.create_time", "<=", $endTime)
            ->count();
    }

    public function avgShow($appCode, $beginTime, $day = 0)
    {
        $endTime = $beginTime + 24 * 60 * 60 - 1;
        $install = Db::connection('tools')->table("app_{$appCode}_active_keep_temp as keep")
            ->where('keep.active_day', date("Ymd", $beginTime + 24 * 60 * 60 * $day))
            ->where('keep.install_day', date("Ymd", $beginTime))
            ->leftJoin("app_{$appCode}_temp as app", "app.android_id", "keep.device_id")
//            ->where('app.vendor', 'OPPO')
            ->whereIn('app.vendor', ['HUAWEI', 'HONOR'])
            ->count();
        $incrementShow = Db::connection('tools')->table("app_{$appCode}_event_ad_show as show")
            ->whereBetween('show.create_time', [$beginTime + 24 * 60 * 60 * $day, $endTime + 24 * 60 * 60 * $day])
            ->leftJoin("app_{$appCode}_temp as app", "app.android_id", "show.android_id")
            ->whereBetween('app.create_time', [$beginTime, $endTime])
//            ->where('app.vendor', 'OPPO')
            ->whereIn('app.vendor', ['HUAWEI', 'HONOR'])
            ->sum("show.times");
        $incrementShow += Db::connection('tools')->table("app_{$appCode}_event_ad_show_temp as show")
            ->whereBetween('show.create_time', [$beginTime + 24 * 60 * 60 * $day, $endTime + 24 * 60 * 60 * $day])
            ->leftJoin("app_{$appCode}_temp as app", "app.android_id", "show.android_id")
            ->whereBetween('app.create_time', [$beginTime, $endTime])
//            ->where('app.vendor', 'OPPO')
            ->whereIn('app.vendor', ['HUAWEI', 'HONOR'])
            ->sum("show.times");

        $imputedRevenue = Db::connection('tools')->table("app_{$appCode}_event_interval_group_temp as eig")
            ->where('eig.start_time', '>=', $beginTime + 24 * 60 * 60 * $day)
            ->where('eig.end_time', '<=', $endTime + 24 * 60 * 60 * $day)
            ->leftJoin("app_{$appCode}_temp as app", "app.android_id", "eig.device_id")
            ->whereBetween('app.create_time', [$beginTime, $endTime])
//            ->where('app.vendor', 'OPPO')
            ->whereIn('app.vendor', ['HUAWEI', 'HONOR'])
            ->sum("eig.amount");
        return [
            'avgShow' => round($install>0?$incrementShow /$install:0, 2),
            'ecpm' => round(($incrementShow > 0 ?$imputedRevenue / $incrementShow:0) * 1000, 2),
            'install' => $install,
        ];
    }
}