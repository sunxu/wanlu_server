<?php

declare(strict_types=1);

namespace App\Command\Temp;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class AdPayback extends HyperfCommand
{
    protected $name = 'temp:adPayback';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:回本率");
        /**
         * 目前在投的品（周到、玲珑、木匣、实用、抽屉、麻雀、睿知、大字体浏览器）10.22-10.24，分厂商（华、米、O、V、其他）：
        1、新增安装arpu
        2、新增安装ecpm
        3、首日回本率（当日自然日）
         * 4、DEU老的arpu（DEU老 当天所有的DEU用户减去当天新增的deu用户）
         */
    }

    protected $appCodes = [
        'zdgjx', 'llwbx', 'mxdd', 'sydd', 'ctdd', 'mqhz', 'rzxh', 'dzlql'
    ];

    protected $beginTime = '2022-10-22 00:00:00';
    protected $endTime = '2022-10-24 00:00:00';

    public function handle()
    {
        $this->info("开始".date("Y-m-d H:i:s"));
        $beginTime = strtotime($this->beginTime);
        for(; $beginTime<=strtotime($this->endTime);){
            foreach ($this->appCodes as $appCode){
                $this->info('doing:'.$appCode.':' . date("Y-m-d H:i:s", $beginTime));
                //新增安装
                $install_huawei = $this->getInstall($beginTime, $appCode, 'huawei');
                $install_xiaomi = $this->getInstall($beginTime, $appCode, 'xiaomi');
                $install_oppo = $this->getInstall($beginTime, $appCode, 'oppo');
                $install_vivo = $this->getInstall($beginTime, $appCode, 'vivo');
                $install_other = $this->getInstall($beginTime, $appCode);
                //新增预估回收
                $revenue_huawei = $this->getRevenue($beginTime, $appCode, 'huawei');
                $revenue_xiaomi = $this->getRevenue($beginTime, $appCode, 'xiaomi');
                $revenue_oppo = $this->getRevenue($beginTime, $appCode, 'oppo');
                $revenue_vivo = $this->getRevenue($beginTime, $appCode, 'vivo');
                $revenue_other = $this->getRevenue($beginTime, $appCode);
                //新增展示数
                $show_huawei = $this->getShow($beginTime, $appCode, 'huawei');
                $show_xiaomi = $this->getShow($beginTime, $appCode, 'xiaomi');
                $show_oppo = $this->getShow($beginTime, $appCode, 'oppo');
                $show_vivo = $this->getShow($beginTime, $appCode, 'vivo');
                $show_other = $this->getShow($beginTime, $appCode);
                //平均消耗
                $cost = Db::table('report_daily_advertiser')
                    ->where('app_code', $appCode)
                    ->where('date', date("Y-m-d", $beginTime))->sum('cost');
                $avg_cost = ($install_huawei + $install_xiaomi + $install_oppo + $install_vivo + $install_other) > 0 ?($cost / ($install_huawei + $install_xiaomi + $install_oppo + $install_vivo + $install_other)) : 0;
                //旧deu
                $deu_old_huawei = $this->getDeuOld($beginTime, $appCode, 'huawei');
                $deu_old_xiaomi = $this->getDeuOld($beginTime, $appCode, 'xiaomi');
                $deu_old_oppo = $this->getDeuOld($beginTime, $appCode, 'oppo');
                $deu_old_vivo = $this->getDeuOld($beginTime, $appCode, 'vivo');
                $deu_old_other = $this->getDeuOld($beginTime, $appCode, 'other');
                //旧预估回收
                $revenue_old_huawei = $this->getRevenueOld($beginTime, $appCode, 'huawei');
                $revenue_old_xiaomi = $this->getRevenueOld($beginTime, $appCode, 'xiaomi');
                $revenue_old_oppo = $this->getRevenueOld($beginTime, $appCode, 'oppo');
                $revenue_old_vivo = $this->getRevenueOld($beginTime, $appCode, 'vivo');
                $revenue_old_other = $this->getRevenueOld($beginTime, $appCode, 'other');

                Db::connection('tools')->table('temp_20221025')->insert([
                    'date' => date('Y-m-d', $beginTime),
                    'appcode' => $appCode,

                    'install_arpu_huawei' => round(($install_huawei > 0 ?$revenue_huawei / $install_huawei:0), 2),
                    'install_arpu_xiaomi' => round(($install_xiaomi > 0 ?$revenue_xiaomi / $install_xiaomi:0), 2),
                    'install_arpu_oppo' => round(($install_oppo > 0 ?$revenue_oppo / $install_oppo:0), 2),
                    'install_arpu_vivo' => round(($install_vivo > 0 ?$revenue_vivo / $install_vivo:0), 2),
                    'install_arpu_other' => round(($install_other > 0 ?$revenue_other / $install_other:0), 2),

                    'install_ecpm_huawei' => round(($show_huawei > 0 ?$revenue_huawei / $show_huawei:0) * 1000, 2),
                    'install_ecpm_xiaomi' => round(($show_xiaomi > 0 ?$revenue_xiaomi / $show_xiaomi:0) * 1000, 2),
                    'install_ecpm_oppo' => round(($show_oppo > 0 ?$revenue_oppo / $show_oppo:0) * 1000, 2),
                    'install_ecpm_vivo' => round(($show_vivo > 0 ?$revenue_vivo / $show_vivo:0) * 1000, 2),
                    'install_ecpm_other' => round(($show_other > 0 ?$revenue_other / $show_other:0) * 1000, 2),

                    'huiben_huawei' => round((($install_huawei * $avg_cost)>0?$revenue_huawei / ($install_huawei * $avg_cost):0), 2),
                    'huiben_xiaomi' => round((($install_xiaomi * $avg_cost)>0?$revenue_xiaomi / ($install_xiaomi * $avg_cost):0), 2),
                    'huiben_oppo' => round((($install_oppo * $avg_cost)>0?$revenue_oppo / ($install_oppo * $avg_cost):0), 2),
                    'huiben_vivo' => round((($install_vivo * $avg_cost)>0?$revenue_vivo / ($install_vivo * $avg_cost):0), 2),
                    'huiben_other' => round((($install_other * $avg_cost)>0?$revenue_other / ($install_other * $avg_cost):0), 2),

                    'deu_old_huawei' => round(($deu_old_huawei > 0 ?$revenue_old_huawei / $deu_old_huawei:0), 2),
                    'deu_old_xiaomi' => round(($deu_old_xiaomi > 0 ?$revenue_old_xiaomi / $deu_old_xiaomi:0), 2),
                    'deu_old_oppo' => round(($deu_old_oppo > 0 ?$revenue_old_oppo / $deu_old_oppo:0), 2),
                    'deu_old_vivo' => round(($deu_old_vivo > 0 ?$revenue_old_vivo / $deu_old_vivo:0), 2),
                    'deu_old_other' => round(($deu_old_other > 0 ?$revenue_old_other / $deu_old_other:0), 2),
                ]);
            }
            $beginTime += 60 * 60 * 24;
        }
    }

    public function getRevenueOld($time, $appCode, $vendor)
    {
        $endTime = $time + 24 * 60 * 60 - 1;
        $model = Db::connection('tools')->table("app_{$appCode}_event_interval_group as eig")
            ->where('eig.start_time', '>=', $time)
            ->where('eig.end_time', '<=', $endTime)
            ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
            ->where('app.create_time', '<', $time);
        switch ($vendor){
            case 'huawei':
                $model = $model->whereIn('app.vendor', ['HUAWEI', 'HONOR']);
                break;
            case 'xiaomi':
                $model = $model->where('app.vendor', 'Xiaomi');
                break;
            case 'oppo':
                $model = $model->where('app.vendor', 'OPPO');
                break;
            case 'vivo':
                $model = $model->where('app.vendor', 'vivo');
                break;
            default:
                $model = $model->whereNotIn('app.vendor', ['HUAWEI', 'HONOR', 'Xiaomi', 'OPPO', 'vivo']);
        }
        return $model->sum("eig.amount");
    }

    public function getDeuOld($time, $appCode, $vendor)
    {
        $model = Db::connection('tools')->table("app_{$appCode}_active_keep as keep")
            ->where('keep.active_day', date("Ymd", $time))
            ->leftJoin("app_{$appCode} as app", "app.android_id", "keep.device_id")
            ->where('keep.is_ad_report', 1)
            ->where('app.create_time', '<', $time);
        switch ($vendor){
            case 'huawei':
                $model = $model->whereIn('app.vendor', ['HUAWEI', 'HONOR']);
                break;
            case 'xiaomi':
                $model = $model->where('app.vendor', 'Xiaomi');
                break;
            case 'oppo':
                $model = $model->where('app.vendor', 'OPPO');
                break;
            case 'vivo':
                $model = $model->where('app.vendor', 'vivo');
                break;
            default:
                $model = $model->whereNotIn('app.vendor', ['HUAWEI', 'HONOR', 'Xiaomi', 'OPPO', 'vivo']);
        }
        return $model->count();
    }

    public function getShow($time, $appCode, $vendor = 'other')
    {
        $endTime = $time + 24 * 60 * 60 - 1;
        $model = Db::connection('tools')->table("app_{$appCode}_event_ad_show as show")
            ->whereBetween('show.create_time', [$time, $endTime])
            ->leftJoin("app_{$appCode} as app", "app.android_id", "show.android_id")
            ->whereBetween('app.create_time', [$time, $endTime]);
        $tempModel = Db::connection('tools')->table("app_{$appCode}_event_ad_show_temp as show")
            ->whereBetween('show.create_time', [$time, $endTime])
            ->leftJoin("app_{$appCode} as app", "app.android_id", "show.android_id")
            ->whereBetween('app.create_time', [$time, $endTime]);
        switch ($vendor){
            case 'huawei':
                $model = $model->whereIn('app.vendor', ['HUAWEI', 'HONOR']);
                $tempModel = $tempModel->whereIn('app.vendor', ['HUAWEI', 'HONOR']);
                break;
            case 'xiaomi':
                $model = $model->where('app.vendor', 'Xiaomi');
                $tempModel = $tempModel->where('app.vendor', 'Xiaomi');
                break;
            case 'oppo':
                $model = $model->where('app.vendor', 'OPPO');
                $tempModel = $tempModel->where('app.vendor', 'OPPO');
                break;
            case 'vivo':
                $model = $model->where('app.vendor', 'vivo');
                $tempModel = $tempModel->where('app.vendor', 'vivo');
                break;
            default:
                $model = $model->whereNotIn('app.vendor', ['HUAWEI', 'HONOR', 'Xiaomi', 'OPPO', 'vivo']);
                $tempModel = $tempModel->whereNotIn('app.vendor', ['HUAWEI', 'HONOR', 'Xiaomi', 'OPPO', 'vivo']);
        }
        return $model->sum("show.times") + $tempModel->sum("show.times");
    }

    public function getRevenue($time, $appCode, $vendor = 'other')
    {
        $endTime = $time + 24 * 60 * 60 - 1;
        $model = Db::connection('tools')->table("app_{$appCode}_event_interval_group as eig")
            ->where('eig.start_time', '>=', $time)
            ->where('eig.end_time', '<=', $endTime)
            ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
            ->whereBetween('app.create_time', [$time, $endTime]);
        switch ($vendor){
            case 'huawei':
                $model = $model->whereIn('app.vendor', ['HUAWEI', 'HONOR']);
                break;
            case 'xiaomi':
                $model = $model->where('app.vendor', 'Xiaomi');
                break;
            case 'oppo':
                $model = $model->where('app.vendor', 'OPPO');
                break;
            case 'vivo':
                $model = $model->where('app.vendor', 'vivo');
                break;
            default:
                $model = $model->whereNotIn('app.vendor', ['HUAWEI', 'HONOR', 'Xiaomi', 'OPPO', 'vivo']);
        }
        return $model->sum("eig.amount");
    }

    public function getInstall($time, $appCode, $vendor = 'other')
    {
        $endTime = $time + 24 * 60 * 60 - 1;
        $model = Db::connection('tools')->table("app_{$appCode}")
            ->whereBetween('create_time', [$time, $endTime]);
        switch ($vendor){
            case 'huawei':
                $model = $model->whereIn('vendor', ['HUAWEI', 'HONOR']);
                break;
            case 'xiaomi':
                $model = $model->where('vendor', 'Xiaomi');
                break;
            case 'oppo':
                $model = $model->where('vendor', 'OPPO');
                break;
            case 'vivo':
                $model = $model->where('vendor', 'vivo');
                break;
            default:
                $model = $model->whereNotIn('vendor', ['HUAWEI', 'HONOR', 'Xiaomi', 'OPPO', 'vivo']);
        }
        return $model->count();
    }
}