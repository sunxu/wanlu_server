<?php

declare(strict_types=1);

namespace App\Command\Temp;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class AdCelue extends HyperfCommand
{
    protected $name = 'temp:adcelue';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:回本率");
        /**
         * 麻雀，11.4-11.6，分广告策略，分版本号的
         * 新增安装数、次留数、新增人均展示、新增ecpm、DEU人均、DEU-ecpm
         *
         * 收夹、快嗖嗖，11.12-11.15，分广告策略，分版本号的
        新增安装数、次留数、新增人均展示、新增ecpm、DEU人均、DEU-ecpm
         */
    }

    protected $appCodes = [
        'mqhz'
    ];
    protected $appInfo = [
        'mqhz' => [
            'version' => ['1.0.1', '1.0.4', '1.0.6', '1.0.7', '1.0.8'],
            'strategy' => [111, 129],
        ],
    ];

    protected $beginTime = '2022-11-08 00:00:00';
    protected $endTime = '2022-11-15 00:00:00';

    public function handle()
    {
        $this->info("开始" . date("Y-m-d H:i:s"));
        $beginTime = strtotime($this->beginTime);
        for (; $beginTime <= strtotime($this->endTime);) {
            foreach ($this->appCodes as $appCode) {
                $this->info('doing:' . $appCode . ':' . date("Y-m-d H:i:s", $beginTime));
                $versions = $this->appInfo[$appCode]['version'];
                $strategys = $this->appInfo[$appCode]['strategy'];

                foreach ($versions as $version) {
                    $this->info("doin:{$version}");
                    foreach ($strategys as $celue) {
                        $this->info("doin:{$celue}");
                        $installed = $this->getInstall($appCode, $beginTime, $celue, $version);
                        $keepInstalled = $this->getInstall($appCode, $beginTime, $celue, $version, 1);
                        $showTimes = $this->getAdShow($appCode, $beginTime, $celue, $version);
                        $huishou = $this->getHuishou($appCode, $beginTime, $celue, $version);
                        $deuShow = $this->getDeuShow($appCode, $beginTime, $celue, $version);
                        $deu = $this->getDeu($appCode, $beginTime, $celue, $version);
                        $deuhuishou = $this->getDeuHuishou($appCode, $beginTime, $celue, $version);
                        Db::connection('tools')->table('temp_20221116')->insert([
                            'date' => date('Y-m-d', $beginTime),
                            'appcode' => $appCode,
                            'strategy' => $celue,
                            'version' => $version,

                            'installed' => $installed,
                            'keep_installed' => $keepInstalled,
                            'avg_show' => round($installed > 0 ? $showTimes / $installed : 0, 2),
                            'ecpm' => round(($showTimes > 0 ? $huishou / $showTimes : 0) * 1000, 2),
                            'deu_avg_show' => round($deu > 0 ? $deuShow / $deu : 0, 2),
                            'deu_ecpm' => round(($deuShow > 0 ? $deuhuishou / $deuShow : 0) * 1000, 2),
                        ]);
                    }
                }

            }
            $beginTime += 60 * 60 * 24;
        }
        $this->info("结束" . date("Y-m-d H:i:s"));
    }

    public function getDeuHuishou($appCode, $beginTime, $celue, $version)
    {
        $endTime = $beginTime + 24 * 60 * 60 - 1;
        return Db::connection('tools')->table("app_{$appCode}_event_interval_group as eig")
            ->where('eig.start_time', '>=', $beginTime)
            ->where('eig.end_time', '<=', $endTime)
            ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
            ->where('app.version', $version)
            ->whereRaw("app.android_id in (select appl.android_id from {$this->getTable($beginTime, $appCode)} as appl where appl.imei->'$.id' = {$celue} and appl.create_time >= {$beginTime} and appl.create_time <= {$endTime})")
//            ->whereExists(function ($query) use ($beginTime, $appCode, $celue, $endTime) {
//                $query->from("{$this->getTable($beginTime, $appCode)} as appl")
//                    ->select('appl.android_id')
//                    ->where('appl.strategy_key', $celue)
//                    ->whereRaw('app.android_id = appl.android_id')
//                    ->whereBetween('appl.create_time', [$beginTime, $endTime]);
//            })
//            ->leftJoin("{$this->getTable($beginTime, $appCode)} as appl", 'appl.android_id', "app.android_id")
//            ->where('appl.strategy_key', $celue)
//            ->whereBetween('appl.create_time', [$beginTime, $endTime])
            ->sum("eig.amount");
    }

    public function getDeu($appCode, $beginTime, $celue, $version)
    {
        $endTime = $beginTime + 24 * 60 * 60 - 1;
        return Db::connection('tools')->table("app_{$appCode}_active_keep as keep")
            ->where('keep.active_day', date("Ymd", $beginTime))
            ->where('keep.is_ad_report', 1)
            ->leftJoin("app_{$appCode} as app", "app.android_id", "keep.device_id")
            ->where('app.version', $version)
            ->whereRaw("app.android_id in (select appl.android_id from {$this->getTable($beginTime, $appCode)} as appl where appl.imei->'$.id' = {$celue} and appl.create_time >= {$beginTime} and appl.create_time <= {$endTime})")

//            ->whereExists(function ($query) use ($beginTime, $appCode, $celue, $endTime) {
//                $query->from("{$this->getTable($beginTime, $appCode)} as appl")
//                    ->select('appl.android_id')
//                    ->where('appl.strategy_key', $celue)
//                    ->whereRaw('app.android_id = appl.android_id')
//                    ->whereBetween('appl.create_time', [$beginTime, $endTime]);
//            })
//            ->leftJoin("{$this->getTable($beginTime, $appCode)} as appl", 'appl.android_id', "app.android_id")
//            ->where('appl.strategy_key', $celue)
//            ->whereBetween('appl.create_time', [$beginTime, $endTime])
            ->count();
    }

    public function getDeuShow($appCode, $beginTime, $celue, $version)
    {
        $endTime = $beginTime + 24 * 60 * 60 - 1;
        $show = Db::connection('tools')->table("app_{$appCode}_event_ad_show as show")
            ->whereBetween('show.create_time', [$beginTime, $endTime])
            ->leftJoin("app_{$appCode} as app", "app.android_id", "show.android_id")
            ->where('app.version', $version)
            ->whereRaw("app.android_id in (select appl.android_id from {$this->getTable($beginTime, $appCode)} as appl where appl.imei->'$.id' = {$celue} and appl.create_time >= {$beginTime} and appl.create_time <= {$endTime})")

//            ->whereExists(function ($query) use ($beginTime, $appCode, $celue, $endTime) {
//                $query->from("{$this->getTable($beginTime, $appCode)} as appl")
//                    ->select('appl.android_id')
//                    ->where('appl.strategy_key', $celue)
//                    ->whereRaw('app.android_id = appl.android_id')
//                    ->whereBetween('appl.create_time', [$beginTime, $endTime]);
//            })
//            ->leftJoin("{$this->getTable($beginTime, $appCode)} as appl", 'appl.android_id', "app.android_id")
//            ->where('appl.strategy_key', $celue)
//            ->whereBetween('appl.create_time', [$beginTime, $endTime])
            ->sum("show.times");
        $show += Db::connection('tools')->table("app_{$appCode}_event_ad_show_temp as show")
            ->whereBetween('show.create_time', [$beginTime, $endTime])
            ->leftJoin("app_{$appCode} as app", "app.android_id", "show.android_id")
            ->where('app.version', $version)
            ->whereRaw("app.android_id in (select appl.android_id from {$this->getTable($beginTime, $appCode)} as appl where appl.imei->'$.id' = {$celue} and appl.create_time >= {$beginTime} and appl.create_time <= {$endTime})")

//            ->whereExists(function ($query) use ($beginTime, $appCode, $celue, $endTime) {
//                $query->from("{$this->getTable($beginTime, $appCode)} as appl")
//                    ->select('appl.android_id')
//                    ->where('appl.strategy_key', $celue)
//                    ->whereRaw('app.android_id = appl.android_id')
//                    ->whereBetween('appl.create_time', [$beginTime, $endTime]);
//            })
//            ->leftJoin("{$this->getTable($beginTime, $appCode)} as appl", 'appl.android_id', "app.android_id")
//            ->where('appl.strategy_key', $celue)
//            ->whereBetween('appl.create_time', [$beginTime, $endTime])
            ->sum("show.times");
        return $show;
    }

    public function getHuishou($appCode, $beginTime, $celue, $version)
    {
        $endTime = $beginTime + 24 * 60 * 60 - 1;
        return Db::connection('tools')->table("app_{$appCode}_event_interval_group as eig")
            ->where('eig.start_time', '>=', $beginTime)
            ->where('eig.end_time', '<=', $endTime)
            ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
            ->whereBetween('app.create_time', [$beginTime, $endTime])
            ->where('app.version', $version)
            ->whereRaw("app.android_id in (select appl.android_id from {$this->getTable($beginTime, $appCode)} as appl where appl.imei->'$.id' = {$celue} and appl.create_time >= {$beginTime} and appl.create_time <= {$endTime})")

//            ->whereExists(function ($query) use ($beginTime, $appCode, $celue, $endTime) {
//                $query->from("{$this->getTable($beginTime, $appCode)} as appl")
//                    ->select('appl.android_id')
//                    ->where('appl.strategy_key', $celue)
//                    ->whereRaw('app.android_id = appl.android_id')
//                    ->whereBetween('appl.create_time', [$beginTime, $endTime]);
//            })
//            ->leftJoin("{$this->getTable($beginTime, $appCode)} as appl", 'appl.android_id', "app.android_id")
//            ->where('appl.strategy_key', $celue)
//            ->whereBetween('appl.create_time', [$beginTime, $endTime])
            ->sum("eig.amount");
    }

    public function getAdShow($appCode, $beginTime, $celue, $version)
    {
        $endTime = $beginTime + 24 * 60 * 60 - 1;
        $incrementShow = Db::connection('tools')->table("app_{$appCode}_event_ad_show as show")
            ->whereBetween('show.create_time', [$beginTime, $endTime])
            ->leftJoin("app_{$appCode} as app", "app.android_id", "show.android_id")
            ->whereBetween('app.create_time', [$beginTime, $endTime])
            ->where('app.version', $version)
            ->whereRaw("app.android_id in (select appl.android_id from {$this->getTable($beginTime, $appCode)} as appl where appl.imei->'$.id' = {$celue} and appl.create_time >= {$beginTime} and appl.create_time <= {$endTime})")

//            ->whereExists(function ($query) use ($beginTime, $appCode, $celue, $endTime) {
//                $query->from("{$this->getTable($beginTime, $appCode)} as appl")
//                    ->select('appl.android_id')
//                    ->where('appl.strategy_key', $celue)
//                    ->whereRaw('app.android_id = appl.android_id')
//                    ->whereBetween('appl.create_time', [$beginTime, $endTime]);
//            })
//            ->leftJoin("{$this->getTable($beginTime, $appCode)} as appl", 'appl.android_id', "app.android_id")
//            ->where('appl.strategy_key', $celue)
//            ->whereBetween('appl.create_time', [$beginTime, $endTime])
            ->sum("show.times");
        $incrementShow += Db::connection('tools')->table("app_{$appCode}_event_ad_show_temp as show")
            ->whereBetween('show.create_time', [$beginTime, $endTime])
            ->leftJoin("app_{$appCode} as app", "app.android_id", "show.android_id")
            ->whereBetween('app.create_time', [$beginTime, $endTime])
            ->where('app.version', $version)
            ->whereRaw("app.android_id in (select appl.android_id from {$this->getTable($beginTime, $appCode)} as appl where appl.imei->'$.id' = {$celue} and appl.create_time >= {$beginTime} and appl.create_time <= {$endTime})")

//            ->whereExists(function ($query) use ($beginTime, $appCode, $celue, $endTime) {
//                $query->from("{$this->getTable($beginTime, $appCode)} as appl")
//                    ->select('appl.android_id')
//                    ->where('appl.strategy_key', $celue)
//                    ->whereRaw('app.android_id = appl.android_id')
//                    ->whereBetween('appl.create_time', [$beginTime, $endTime]);
//            })
//            ->leftJoin("{$this->getTable($beginTime, $appCode)} as appl", 'appl.android_id', "app.android_id")
//            ->where('appl.strategy_key', $celue)
//            ->whereBetween('appl.create_time', [$beginTime, $endTime])
            ->sum("show.times");

        return $incrementShow;
    }

    public function getInstall($appCode, $beginTime, $celue, $version, $day = 0)
    {
        $endTime = $beginTime + 24 * 60 * 60 - 1;
        $install = Db::connection('tools')->table("app_{$appCode}_active_keep as keep")
            ->where('keep.active_day', date("Ymd", $beginTime + 24 * 60 * 60 * $day))
            ->where('keep.install_day', date("Ymd", $beginTime))
            ->leftJoin("app_{$appCode} as app", "app.android_id", "keep.device_id")
            ->where('app.version', $version)
            ->leftJoin("{$this->getTable($beginTime, $appCode)} as appl", 'appl.android_id', "keep.device_id")
            ->whereRaw("appl.imei->'$.id' = {$celue}")
//            ->where('appl.strategy_key', $celue)
            ->whereBetween('appl.create_time', [$beginTime, $endTime])
            ->count(Db::raw("DISTINCT keep.device_id"));

        return $install;
    }

    public function getTable($date, $appcode)
    {
        if ($date < strtotime(date("Y-m-d 00:00:00", time() - 60 * 60 * 24 * 2))) {
            return "app_{$appcode}_log_temp";
        }
        return "app_{$appcode}_log";

    }
}