<?php

declare(strict_types=1);

namespace App\Command\Temp;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class AppJudge extends HyperfCommand
{
    protected $name = 'temp:appJudge';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:回本率");
        /**
         * 10.27-11.2，麻雀、木匣、玲珑、周到
        各个策略（正常、巡检、审核）的占比，以及对应每天的归因成功率
         */
    }

    protected $appCodes = [
        'zdgjx', 'llwbx', 'mxdd', 'mqhz',
    ];

    protected $beginTime = '2022-10-27 00:00:00';
    protected $endTime = '2022-11-02 00:00:00';

    public function handle()
    {
        $this->info("开始".date("Y-m-d H:i:s"));
        $beginTime = strtotime($this->beginTime);
        for(; $beginTime<=strtotime($this->endTime);){
            foreach ($this->appCodes as $appCode){
                $this->info('doing:'.$appCode.':' . date("Y-m-d H:i:s", $beginTime));
                $installed = $this->getInstall($appCode, $beginTime);
                $judged = $this->getJudge($appCode, $beginTime);
                $guiyin = $this->getGuiyin($appCode, $beginTime);
                Db::connection('tools')->table('temp_20221103')->insert([
                    'date' => date('Y-m-d', $beginTime),
                    'appcode' => $appCode,

                    'audit' => $guiyin['audit'],
                    'common' => $guiyin['common'],
                    'patrol' => $guiyin['patrol'],

                    'judge' => round($judged / $installed * 100, 2)
                ]);
            }
            $beginTime += 60 * 60 * 24;
        }
    }

    public function getInstall($appCode, $beginTime)
    {
        $endTime = $beginTime + 24 * 60 * 60 - 1;
        return Db::connection('tools')->table("app_{$appCode} as app")
            ->whereBetween('app.create_time', [$beginTime, $endTime])
            ->count();
    }

    public function getJudge($appCode, $beginTime)
    {
        $endTime = $beginTime + 24 * 60 * 60 - 1;
        return Db::connection('tools')->table("app_{$appCode} as app")
            ->whereBetween('app.create_time', [$beginTime, $endTime])
            ->where('ad_time', '>', 0)
            ->count();
    }

    public function getGuiyin($appCode, $beginTime)
    {
        $endTime = $beginTime + 24 * 60 * 60 - 1;

        $tableName = "app_{$appCode}_log";
        if($beginTime < strtotime('2022-11-01 00:00:00')){
            $tableName = "app_{$appCode}_log_temp";
        }
        $total = Db::connection('tools')->table($tableName)
            ->whereBetween('create_time', [$beginTime, $endTime])
            ->count(Db::raw("DISTINCT android_id"));

        $common = Db::connection('tools')->table($tableName)
            ->whereBetween('create_time', [$beginTime, $endTime])
            ->where('strategy_key', 'common')
            ->count(Db::raw("DISTINCT android_id"));

        $patrol = Db::connection('tools')->table($tableName)
            ->whereBetween('create_time', [$beginTime, $endTime])
            ->where('strategy_key', 'patrol')
            ->count(Db::raw("DISTINCT android_id"));

        $audit = Db::connection('tools')->table($tableName)
            ->whereBetween('create_time', [$beginTime, $endTime])
            ->where('strategy_key', 'audit')
            ->count(Db::raw("DISTINCT android_id"));

        return [
            'audit' => round( $audit / $total * 100, 2),
            'common' => round( $common / $total * 100, 2),
            'patrol' => round( $patrol / $total * 100, 2)
        ];
    }

}