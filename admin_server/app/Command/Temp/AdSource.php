<?php

declare(strict_types=1);

namespace App\Command\Temp;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class AdSource extends HyperfCommand
{
    protected $name = 'temp:adSourceLaunch';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:统计ecpm");
        /**
         * 玲珑的，10.17-10.19，区分投放渠道投放方式（巨量关键行为、巨量双出、快手关键行为）：
        1、分厂商的（华米OV），新增安装用户的人均广告展示
        2、分厂商的（华米OV），deu用户的人均广告展示
        3、DEU的arpu
        4、新增安装的ecpm
        5、DEU的ecpm
         */
    }

    protected $appCode = 'llwbx';

    public function handle()
    {
        $this->info("开始".date("Y-m-d H:i:s"));
        $beginTime = strtotime('2022-10-17 00:00:00');
        for(; $beginTime<=strtotime('2022-10-19 00:00:00');){
            $this->info("----".date("Y-m-d H:i:s", $beginTime));
            //- launch_type 投放方式0-次留双出 1-关键行为
            //-- source [1]巨量[2]快手
            //
            Db::connection('tools')->table('temp_20221020')->insert([
                'date' => date('Y-m-d', $beginTime),
                'jl_key_huawei_avg_show' => $this->getAvgShow($beginTime,1, 1, 'huawei'),
                'jl_key_xiaomi_avg_show' => $this->getAvgShow($beginTime,1, 1, 'xiaomi'),
                'jl_key_oppo_avg_show' => $this->getAvgShow($beginTime,1, 1, 'oppo'),
                'jl_key_vivo_avg_show' => $this->getAvgShow($beginTime,1, 1, 'vivo'),

                'jl_shuang_huawei_avg_show' => $this->getAvgShow($beginTime,1, 0, 'huawei'),
                'jl_shuang_xiaomi_avg_show' => $this->getAvgShow($beginTime,1, 0, 'xiaomi'),
                'jl_shuang_oppo_avg_show' => $this->getAvgShow($beginTime,1, 0, 'oppo'),
                'jl_shuang_vivo_avg_show' => $this->getAvgShow($beginTime,1, 0, 'vivo'),

                'ks_key_huawei_avg_show' => $this->getAvgShow($beginTime,2, 1, 'huawei'),
                'ks_key_xiaomi_avg_show' => $this->getAvgShow($beginTime,2, 1, 'xiaomi'),
                'ks_key_oppo_avg_show' => $this->getAvgShow($beginTime,2, 1, 'oppo'),
                'ks_key_vivo_avg_show' => $this->getAvgShow($beginTime,2, 1, 'vivo'),

                'jl_shuang_huawei_avg_deu' => $this->getAvgDeu($beginTime,1, 0, 'huawei'),
                'jl_shuang_xiaomi_avg_deu' => $this->getAvgDeu($beginTime,1, 0, 'xiaomi'),
                'jl_shuang_oppo_avg_deu' => $this->getAvgDeu($beginTime,1, 0, 'oppo'),
                'jl_shuang_vivo_avg_deu' => $this->getAvgDeu($beginTime,1, 0, 'vivo'),

                'ks_key_huawei_avg_deu' => $this->getAvgDeu($beginTime,2, 1, 'huawei'),
                'ks_key_xiaomi_avg_deu' => $this->getAvgDeu($beginTime,2, 1, 'xiaomi'),
                'ks_key_oppo_avg_deu' => $this->getAvgDeu($beginTime,2, 1, 'oppo'),
                'ks_key_vivo_avg_deu' => $this->getAvgDeu($beginTime,2, 1, 'vivo'),

                'jl_key_huawei_avg_deu' => $this->getAvgDeu($beginTime,1, 1, 'huawei'),
                'jl_key_xiaomi_avg_deu' => $this->getAvgDeu($beginTime,1, 1, 'xiaomi'),
                'jl_key_oppo_avg_deu' => $this->getAvgDeu($beginTime,1, 1, 'oppo'),
                'jl_key_vivo_avg_deu' => $this->getAvgDeu($beginTime,1, 1, 'vivo'),

                'jl_key_deu_arpu' => $this->getEduArpu($beginTime,1, 1,),
                'jl_shuang_deu_arpu' => $this->getEduArpu($beginTime,1, 0,),
                'ks_key_deu_arpu' => $this->getEduArpu($beginTime,2, 1,),

                'jl_key_ecpm' => $this->getecpm($beginTime,1, 1),
                'jl_shuang_ecpm' => $this->getecpm($beginTime,1, 0),
                'ks_key_ecpm' => $this->getecpm($beginTime,2, 1),

                'jl_key_deu_ecpm' => $this->getdeuecpm($beginTime,1, 1),
                'jl_shuang_deu_ecpm' => $this->getdeuecpm($beginTime,1, 0),
                'ks_key_deu_ecpm' => $this->getdeuecpm($beginTime,2, 1),
            ]);
            sleep(1);
            $beginTime += 60 * 60 * 24;
        }
    }

    public function getdeuecpm($time, $source, $type)
    {
        $endTime = $time + 60 * 60 * 24 - 1;
        $imputedRevenue = Db::connection('tools')->table("app_{$this->appCode}_event_interval_group as eig")
            ->where('eig.start_time', '>=', $time)
            ->where('eig.end_time', '<=', $endTime)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "eig.device_id")
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type)
            ->sum("eig.amount");
        $show = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show as show")
            ->where("show.create_time", ">=", $time)
            ->where("show.create_time", "<=", $endTime)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "show.android_id")
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type)->sum("show.times");
        $show += Db::connection('tools')->table("app_{$this->appCode}_event_ad_show_temp as show")
            ->where("show.create_time", ">=", $time)
            ->where("show.create_time", "<=", $endTime)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "show.android_id")
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type)->sum("show.times");
        return round($show < 0?0:$imputedRevenue/$show*1000, 2);
    }

    public function getecpm($time, $source, $type)
    {
        $endTime = $time + 60 * 60 * 24 - 1;
        $imputedRevenue = Db::connection('tools')->table("app_{$this->appCode}_event_interval_group as eig")
            ->where('eig.start_time', '>=', $time)
            ->where('eig.end_time', '<=', $endTime)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "eig.device_id")
            ->where("app.create_time", ">=", $time)
            ->where("app.create_time", "<=", $endTime)
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type)
            ->sum("eig.amount");
        $show = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show as show")
            ->where("show.create_time", ">=", $time)
            ->where("show.create_time", "<=", $endTime)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "show.android_id")
            ->where("app.create_time", ">=", $time)
            ->where("app.create_time", "<=", $endTime)
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type)->sum("show.times");
        $show += Db::connection('tools')->table("app_{$this->appCode}_event_ad_show_temp as show")
            ->where("show.create_time", ">=", $time)
            ->where("show.create_time", "<=", $endTime)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "show.android_id")
            ->where("app.create_time", ">=", $time)
            ->where("app.create_time", "<=", $endTime)
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type)->sum("show.times");
        return round($show < 0?0:$imputedRevenue/$show*1000, 2);
    }

    public function getEduArpu($time, $source, $type)
    {
        $endTime = $time + 60 * 60 * 24 - 1;
        $edu = Db::connection('tools')->table("app_{$this->appCode}_active_keep as adkeep")
            ->where('adkeep.active_day', date("Ymd", $time))
            ->where('adkeep.is_ad_report', 1)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "adkeep.device_id")
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type)->count();
        $imputedRevenue = Db::connection('tools')->table("app_{$this->appCode}_event_interval_group as eig")
            ->where('eig.start_time', '>=', $time)
            ->where('eig.end_time', '<=', $endTime)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "eig.device_id")
            ->where("app.create_time", ">=", $time)
            ->where("app.create_time", "<=", $endTime)
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type)
            ->sum("eig.amount");

        return round($edu < 0?0:$imputedRevenue/$edu, 2);
    }

    public function getAvgShow($time, $source, $type , $vendor)
    {
        $endTime = $time + 60 * 60 * 24 - 1;
        $install = Db::connection('tools')->table("app_{$this->appCode} as app")
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("app.create_time", ">=", $time)
            ->where("app.create_time", "<=", $endTime)
            ->where("app.source", $source)
            ->where("madv.launch_type", $type);
        $show = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show as show")
            ->where("show.create_time", ">=", $time)
            ->where("show.create_time", "<=", $endTime)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "show.android_id")
            ->where("app.create_time", ">=", $time)
            ->where("app.create_time", "<=", $endTime)
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type);
        $showTmp = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show_temp as show")
            ->where("show.create_time", ">=", $time)
            ->where("show.create_time", "<=", $endTime)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "show.android_id")
            ->where("app.create_time", ">=", $time)
            ->where("app.create_time", "<=", $endTime)
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type);
        switch ($vendor){
            case 'huawei':
                $install = $install->whereIn('app.vendor', ['HUAWEI', 'HONOR'])->count();
                $show = $show->whereIn('app.vendor', ['HUAWEI', 'HONOR'])->sum("show.times");
                $showTmp = $showTmp->whereIn('app.vendor', ['HUAWEI', 'HONOR'])->sum("show.times");
                break;
            case 'xiaomi':
                $install = $install->where('app.vendor', 'Xiaomi')->count();
                $show = $show->where('app.vendor', 'Xiaomi')->sum("show.times");
                $showTmp = $showTmp->where('app.vendor', 'Xiaomi')->sum("show.times");
                break;
            case 'oppo':
                $install = $install->where('app.vendor', 'OPPO')->count();
                $show = $show->where('app.vendor', 'OPPO')->sum("show.times");
                $showTmp = $showTmp->where('app.vendor', 'OPPO')->sum("show.times");
                break;
            case 'vivo':
                $install = $install->where('app.vendor', 'Vivo')->count();
                $show = $show->where('app.vendor', 'Vivo')->sum("show.times");
                $showTmp = $showTmp->where('app.vendor', 'Vivo')->sum("show.times");
                break;
        }
        return round($install < 0?0:($show+$showTmp)/$install, 2);
    }

    public function getAvgDeu($time, $source, $type , $vendor)
    {
        $endTime = $time + 60 * 60 * 24 - 1;
        $edu = Db::connection('tools')->table("app_{$this->appCode}_active_keep as adkeep")
            ->where('adkeep.active_day', date("Ymd", $time))
            ->where('adkeep.is_ad_report', 1)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "adkeep.device_id")
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type);
        $show = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show as show")
            ->where("show.create_time", ">=", $time)
            ->where("show.create_time", "<=", $endTime)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "show.android_id")
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type);
        $showTmp = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show_temp as show")
            ->where("show.create_time", ">=", $time)
            ->where("show.create_time", "<=", $endTime)
            ->leftJoin("app_{$this->appCode} as app", "app.android_id", "show.android_id")
            ->where("app.source", $source)
            ->leftJoin("mkt_advertiser as madv", "madv.advertiser_id", "=", "app.account_id")
            ->where("madv.launch_type", $type);
        switch ($vendor){
            case 'huawei':
                $edu = $edu->whereIn('app.vendor', ['HUAWEI', 'HONOR'])->count();
                $show = $show->whereIn('app.vendor', ['HUAWEI', 'HONOR'])->sum("show.times");
                $showTmp = $showTmp->whereIn('app.vendor', ['HUAWEI', 'HONOR'])->sum("show.times");
                break;
            case 'xiaomi':
                $edu = $edu->where('app.vendor', 'Xiaomi')->count();
                $show = $show->where('app.vendor', 'Xiaomi')->sum("show.times");
                $showTmp = $showTmp->where('app.vendor', 'Xiaomi')->sum("show.times");
                break;
            case 'oppo':
                $edu = $edu->where('app.vendor', 'OPPO')->count();
                $show = $show->where('app.vendor', 'OPPO')->sum("show.times");
                $showTmp = $showTmp->where('app.vendor', 'OPPO')->sum("show.times");
                break;
            case 'vivo':
                $edu = $edu->where('app.vendor', 'Vivo')->count();
                $show = $show->where('app.vendor', 'Vivo')->sum("show.times");
                $showTmp = $showTmp->where('app.vendor', 'Vivo')->sum("show.times");
                break;
        }
        return round($edu < 0?0:($show+$showTmp)/$edu, 2);
    }
}