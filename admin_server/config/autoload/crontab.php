<?php

declare(strict_types=1);

use App\Service\AppService;
use App\Service\Cash\DeviceService;
use App\Service\Cash\EcpmService;
use App\Service\Cash\LiveService;
use App\Service\Cash\RevenueService;
use App\Service\Cash\ShowService;
use App\Service\Mkt\AutoAppServiceV2;
use App\Service\Mkt\AutoPlanService;
use App\Service\Mkt\AutoPlanServiceV2;
use App\Service\Paid\PaidService;
use App\Service\Placement\LaunchBaseService;
use App\Service\Product\ProductService;
use Hyperf\Crontab\Crontab;

return [
    // 是否开启定时任务
    'enable' => env('CRONTAB_ENABLE', false),
    'crontab' => [
//        // 工具日报统计 每天凌晨执行一次
//        (new Crontab())->setName('DailyDataCrontab')->setRule('30 00 * * *')->setCallback([\App\Crontab\DailyDataCrontab::class, 'execute'])->setMemo('工具日报统计')->setOnOneServer(true)->setSingleton(true),
//        // 工具日报发送 每天8点执行一次
//        (new Crontab())->setName('DailySendCrontab')->setRule('00 08 * * *')->setCallback([\App\Crontab\DailySendCrontab::class, 'execute'])->setMemo('工具日报发送')->setOnOneServer(true)->setSingleton(true),
        // 华为审核通知 每30分钟一次
        (new Crontab())->setName('appAuditHuawei')->setRule('0/30 * * * *')
            ->setCallback([AppService::class, 'auditNotify', ['huawei']])->setMemo('华为审核通知')
            ->setOnOneServer(true)->setSingleton(true),
        // oppo审核通知 每30分钟一次
        (new Crontab())->setName('appAuditOppo')->setRule('0/30 * * * *')
            ->setCallback([AppService::class, 'auditNotify', ['oppo']])->setMemo('oppo审核通知')
            ->setOnOneServer(true)->setSingleton(true),
        // vivo审核通知 每两个小时执行一次
        (new Crontab())->setName('appAuditVivo')->setRule('00 0/2 * * *')
            ->setCallback([AppService::class, 'auditNotify', ['vivo']])->setMemo('vivo审核通知')
            ->setOnOneServer(true)->setSingleton(true),

        // Mkt刷新Token 每10分钟
        (new Crontab())->setType('command')->setName('mkt:authToken')->setRule('*/10 * * * *')
            ->setCallback([
                'command' => 'mkt:authToken'
            ])->setMemo('MKT刷新token')
            ->setOnOneServer(true)->setSingleton(true),
        // Mkt刷新帐户 每日一次
        (new Crontab())->setType('command')->setName('mkt:accountList')->setRule('12 02 * * *')
            ->setCallback([
                'command' => 'mkt:accountList'
            ])->setMemo('MKT刷新帐户')
            ->setOnOneServer(true)->setSingleton(true),
        // Mkt刷新广告主 每小时一次
        (new Crontab())->setType('command')->setName('mkt:advertiserList')->setRule('13,43 * * * *')
            ->setCallback([
                'command' => 'mkt:advertiserList'
            ])->setMemo('MTK刷新广告主')
            ->setOnOneServer(true)->setSingleton(true),
        // Mkt刷新广告主预算
        (new Crontab())->setType('command')->setName('mkt:advertiserBudget')->setRule('08,38 * * * *')
            ->setCallback([
                'command' => 'mkt:advertiserBudget'
            ])->setMemo('MTK刷新广告主预算')
            ->setOnOneServer(true)->setSingleton(true),

        // START---------MKT刷新广告主下属层级（项目、广告、计划） 每30分钟一次-------------
        (new Crontab())->setType('command')->setName('mkt:advertiserItemList-project')->setRule('03,33 * * * *')
            ->setCallback([
                'command' => 'mkt:advertiserItemList',
                'item' => 'project'
            ])->setMemo('MKT刷新-项目')
            ->setOnOneServer(true)->setSingleton(true),
        (new Crontab())->setType('command')->setName('mkt:advertiserItemList-ad')->setRule('06,36 * * * *')
            ->setCallback([
                'command' => 'mkt:advertiserItemList',
                'item' => 'ad'
            ])->setMemo('MKT刷新-广告')
            ->setOnOneServer(true)->setSingleton(true),
        (new Crontab())->setType('command')->setName('mkt:advertiserItemList-plan')->setRule('13,43 * * * *')
            ->setCallback([
                'command' => 'mkt:advertiserItemList',
                'item' => 'plan'
            ])->setMemo('MKT刷新-计划')
            ->setOnOneServer(true)->setSingleton(true),
        // END---------MKT刷新广告主下属层级（项目、广告、计划） 每30分钟一次-------------

        // START---------MKT日报-广告主-------------
        // 当日每10分钟一次
        (new Crontab())->setType('command')->setName('mkt:reportDailyItem-advertiser-today')->setRule('01,11,21,31,41,51 * * * *')
            ->setCallback([
                'command' => 'mkt:reportDailyItem',
                'item' => 'advertiser',
                'date' => 'today'
            ])->setMemo('MKT日报-广告主-当日')
            ->setOnOneServer(true)->setSingleton(true),
        (new Crontab())->setType('command')->setName('mkt:reportHourlyItem-advertiser-today')->setRule('03,33 * * * *')
            ->setCallback([
                'command' => 'mkt:reportHourlyItem',
                'item' => 'advertiser',
                'date' => 'today'
            ])->setMemo('MKT日报-广告主-当日')
            ->setOnOneServer(true)->setSingleton(true),
        // 昨日 第二天10点后数据稳定
        (new Crontab())->setType('command')->setName('mkt:reportDailyItem-advertiser-yesterday')->setRule('01 5,10 * * *')
            ->setCallback([
                'command' => 'mkt:reportDailyItem',
                'item' => 'advertiser',
                'date' => 'yesterday'
            ])->setMemo('MKT日报-广告主-昨日')
            ->setOnOneServer(true)->setSingleton(true),
        (new Crontab())->setType('command')->setName('mkt:reportHourlyItem-advertiser-yesterday')->setRule('07 5,10 * * *')
            ->setCallback([
                'command' => 'mkt:reportHourlyItem',
                'item' => 'advertiser',
                'date' => 'yesterday'
            ])->setMemo('MKT日报-广告主-昨日')
            ->setOnOneServer(true)->setSingleton(true),
        // 刷新次留数据
        (new Crontab())->setType('command')->setName('mkt:reportDailyItem-advertiser-beforeYesterday')->setRule('08 2 * * *')
            ->setCallback([
                'command' => 'mkt:reportDailyItem',
                'item' => 'advertiser',
                'date' => 'beforeYesterday'
            ])->setMemo('MKT日报-广告主-前日')
            ->setOnOneServer(true)->setSingleton(true),
        // END---------MKT日报-广告主-------------

        // START---------MKT日报-项目-------------
        // 当日每10分钟一次
        (new Crontab())->setType('command')->setName('mkt:reportDailyItem-project-today')->setRule('03,13,23,33,43,53 * * * *')
            ->setCallback([
                'command' => 'mkt:reportDailyItem',
                'item' => 'project',
                'date' => 'today'
            ])->setMemo('MKT日报-项目-当日')
            ->setOnOneServer(true)->setSingleton(true),
        // 昨日报表 第二天10点后数据稳定
        (new Crontab())->setType('command')->setName('mkt:reportDailyItem-project-yesterday')->setRule('13 5,10 * * *')
            ->setCallback([
                'command' => 'mkt:reportDailyItem',
                'item' => 'project',
                'date' => 'yesterday'
            ])->setMemo('MKT日报-项目-昨日')
            ->setOnOneServer(true)->setSingleton(true),
        // 刷新次留数据
        (new Crontab())->setType('command')->setName('mkt:reportDailyItem-project-beforeYesterday')->setRule('13 4 * * *')
            ->setCallback([
                'command' => 'mkt:reportDailyItem',
                'item' => 'project',
                'date' => 'beforeYesterday'
            ])->setMemo('MKT日报-项目-前日')
            ->setOnOneServer(true)->setSingleton(true),
        // END---------MKT日报-项目-------------

        // START---------MKT日报-广告-------------
        // 当日每10分钟一次
        (new Crontab())->setType('command')->setName('mkt:reportDailyItem-ad-today')->setRule('05,15,25,35,45,55 * * * *')
            ->setCallback([
                'command' => 'mkt:reportDailyItem',
                'item' => 'ad',
                'date' => 'today'
            ])->setMemo('MKT日报-项目-当日')
            ->setOnOneServer(true)->setSingleton(true),
        // 昨日报表 第二天10点后数据稳定
        (new Crontab())->setType('command')->setName('mkt:reportDailyItem-ad-yesterday')->setRule('15 5,10 * * *')
            ->setCallback([
                'command' => 'mkt:reportDailyItem',
                'item' => 'ad',
                'date' => 'yesterday'
            ])->setMemo('MKT日报-项目-昨日')
            ->setOnOneServer(true)->setSingleton(true),
        // 刷新次留数据
        (new Crontab())->setType('command')->setName('mkt:reportDailyItem-ad-beforeYesterday')->setRule('15 4 * * *')
            ->setCallback([
                'command' => 'mkt:reportDailyItem',
                'item' => 'ad',
                'date' => 'beforeYesterday'
            ])->setMemo('MKT日报-项目-前日')
            ->setOnOneServer(true)->setSingleton(true),
        // END---------MKT日报-广告-------------

        // START---------MKT日报-计划-------------
        // 日报表每10分钟一次
        (new Crontab())->setType('command')->setName('mkt:reportDailyItem-plan-today')->setRule('07,17,27,37,47,57 * * * *')
            ->setCallback([
                'command' => 'mkt:reportDailyItem',
                'item' => 'plan',
                'date' => 'today'
            ])->setMemo('mkt日报表-计划')
            ->setOnOneServer(true)->setSingleton(true),
        // 昨日报表 第二天10点后数据稳定
        (new Crontab())->setType('command')->setName('mkt:reportDailyItem-plan-yesterday')->setRule('17 5,10 * * *')
            ->setCallback([
                'command' => 'mkt:reportDailyItem',
                'item' => 'plan',
                'date' => 'yesterday'
            ])->setMemo('mkt昨日报表-计划')
            ->setOnOneServer(true)->setSingleton(true),
        // 刷新次留数据
        (new Crontab())->setType('command')->setName('mkt:reportDailyItem-plan-beforeYesterday')->setRule('17 4 * * *')
            ->setCallback([
                'command' => 'mkt:reportDailyItem',
                'item' => 'plan',
                'date' => 'beforeYesterday'
            ])->setMemo('mkt前日报表-计划')
            ->setOnOneServer(true)->setSingleton(true),
        // END---------MKT日报-计划-------------
        // 投放成本监控
        (new Crontab())->setType('command')->setName('mkt:warning')->setRule('06,26,46 * * * *')
            ->setCallback([
                'command' => 'mkt:warning'
            ])->setMemo('mkt广告主主成本警告')
            ->setOnOneServer(true)->setSingleton(true),
        // 投放状态监控
//        (new Crontab())->setType('command')->setName('mkt:putStatus')->setRule('*/10 * * * *')
//            ->setCallback([
//                'command' => 'mkt:putStatus'
//            ])->setMemo('应用投放状态检测')
//            ->setOnOneServer(true)->setSingleton(true),

        // 投放自动检测 每20分钟执行一次
//        (new Crontab())->setType('command')->setName('mkt:rulePlan')->setRule('*/20 * * * *')
//            ->setCallback([
//                'command' => 'mkt:rulePlan',
//            ])->setMemo('mkt-计划规则')
//            ->setOnOneServer(true)->setSingleton(true),

        // 媒体-回收
        (new Crontab())->setType('command')->setName('media:getIncome-yesterday')->setRule('22 6,8,10,12,16,22 * * *')
            ->setCallback([
                'command' => 'media:getIncome',
                'date' => 'yesterday'
            ])->setMemo('媒体昨日回收')
            ->setOnOneServer(true)->setSingleton(true),
        // 媒体-回收-穿山甲
        (new Crontab())->setType('command')->setName('media:getIncome-today')->setRule('12,42 3-23 * * *')
            ->setCallback([
                'command' => 'media:getIncome',
                'date' => 'today',
                'media' => ['csj']
            ])->setMemo('媒体回收-穿山甲')
            ->setOnOneServer(true)->setSingleton(true),
        // 媒体-广告列表
        (new Crontab())->setType('command')->setName('media:adList')->setRule('30 9,12,16,18 * * *')
            ->setCallback([
                'command' => 'media:adList'
            ])->setMemo('媒体代码位列表')
            ->setOnOneServer(true)->setSingleton(true),
        // 媒体-广告检查
        (new Crontab())->setType('command')->setName('media:checkAd')->setRule('30 10,13,17,19 * * *')
            ->setCallback([
                'command' => 'media:checkAd'
            ])->setMemo('媒体代码位检查')
            ->setOnOneServer(true)->setSingleton(true),
        // 媒体-应用关联
        (new Crontab())->setType('command')->setName('media:appCode')->setRule('10 * * * *')
            ->setCallback([
                'command' => 'media:appCode'
            ])->setMemo('媒体应用关联')
            ->setOnOneServer(true)->setSingleton(true),
        // 媒体-应用回收-自投
        (new Crontab())->setType('command')->setName('media:dailyApp')->setRule('40 9,10,11,14,16,20 * * *')
            ->setCallback([
                'command' => 'media:dailyApp',
                'date' => 'yesterday'
            ])->setMemo('媒体-应用回收')
            ->setOnOneServer(true)->setSingleton(true),
        // 投放-投放数据-代投
        (new Crontab())->setType('command')->setName('mkt:lianyun-yesterday')->setRule('10 2,8,10 * * *')
            ->setCallback([
                'command' => 'mkt:lianyun',
                'date' => 'yesterday'
            ])->setMemo('mkt昨日报表-联运')
            ->setOnOneServer(true)->setSingleton(true),
        // 荣耀已接入公共 OpenAPI 同步和报表任务，停用旧网页 Token 抓取任务。
        // 媒体-应用回收-代投
        (new Crontab())->setType('command')->setName('media:dailyLianyun')->setRule('40 9,10,11,14,16,20 * * *')
            ->setCallback([
                'command' => 'media:dailyLianyun',
                'date' => 'yesterday'
            ])->setMemo('媒体-应用回收')
            ->setOnOneServer(true)->setSingleton(true),
        // 媒体-主体回收
        (new Crontab())->setType('command')->setName('media:dailyCompany')->setRule('40 9,10,11,14,16,20 * * *')
            ->setCallback([
                'command' => 'media:dailyCompany',
                'date' => 'yesterday'
            ])->setMemo('媒体-主体回收')
            ->setOnOneServer(true)->setSingleton(true),
        // 归因统计报表
        (new Crontab())->setType('command')->setName('report:guiyin-today')->setRule('55 * * * *')
            ->setCallback([
                'command' => 'report:guiyin',
                'date' => 'today',
            ])->setMemo('归因统计')
            ->setOnOneServer(true)->setSingleton(true),
        // 归因统计报表
        (new Crontab())->setType('command')->setName('report:guiyin-yesterday')->setRule('0 3 * * *')
            ->setCallback([
                'command' => 'report:guiyin',
                'date' => 'yesterday',
            ])->setMemo('归因统计')
            ->setOnOneServer(true)->setSingleton(true),

        // 工具数据播报统计 每天凌晨执行一次
        (new Crontab())->setName('DailyLaunchBase')->setRule('00 7 * * *')
            ->setCallback([LaunchBaseService::class, 'schedule'])->setMemo('工具数据播报统计')
            ->setOnOneServer(true)->setSingleton(true),
        (new Crontab())->setName('DailyLaunchBaseScheduleRevenue')->setRule('30 10,12,15 * * *')
            ->setCallback([LaunchBaseService::class, 'scheduleRevenue'])->setMemo('工具数据播报统计-刷新媒体回收')
            ->setOnOneServer(true)->setSingleton(true),
        // 工具数据播报统计发送 每天8点执行一次
        (new Crontab())->setName('DailyLaunchBaseSend')->setRule('30 8 * * *')
            ->setCallback([LaunchBaseService::class, 'sendReport'])->setMemo('工具数据播报统计发送')
            ->setOnOneServer(true)->setSingleton(true),
        //充值统计 每天凌晨执行一次
        (new Crontab())->setName('reportDailyPaid')->setRule('00 10 * * *')
            ->setCallback([PaidService::class, 'reportDaily'])->setMemo('充值统计（昨天）')
            ->setOnOneServer(true)->setSingleton(true),
        //充值统计 每30分钟执行一次
        (new Crontab())->setName('reportTodayPaid')->setRule('30 * * * *')
            ->setCallback([PaidService::class, 'reportToday'])->setMemo('充值统计（当天）')
            ->setOnOneServer(true)->setSingleton(true),


        //心跳统计
        (new Crontab())->setName('middleLive')->setRule('00 4 * * *')
            ->setCallback([LiveService::class, 'scheduleLive'])->setMemo('心跳统计')
            ->setOnOneServer(true)->setSingleton(true),
        (new Crontab())->setName('middleLiveToday')->setRule('00 5-23/2 * * *')
            ->setCallback([LiveService::class, 'scheduleLiveToday'])->setMemo('心跳统计（当天）')
            ->setOnOneServer(true)->setSingleton(true),

        //ecpm
        (new Crontab())->setName('scheduleEcpmYesterday')->setRule('00 4 * * *')
            ->setCallback([EcpmService::class, 'scheduleEcpmYesterday'])->setMemo('ecpm（昨天）')
            ->setOnOneServer(true)->setSingleton(true),
        (new Crontab())->setName('scheduleEcpmToday')->setRule('10 5-23/2 * * *')
            ->setCallback([EcpmService::class, 'scheduleEcpmToday'])->setMemo('ecpm（当天）')
            ->setOnOneServer(true)->setSingleton(true),
        //回收
        (new Crontab())->setName('scheduleRevenueYesterday')->setRule('00 4 * * *')
            ->setCallback([RevenueService::class, 'scheduleRevenueYesterday'])->setMemo('回收（昨天）')
            ->setOnOneServer(true)->setSingleton(true),
        (new Crontab())->setName('scheduleRevenueToday')->setRule('20 5-23 * * *')
            ->setCallback([RevenueService::class, 'scheduleRevenueToday'])->setMemo('回收（当天）')
            ->setOnOneServer(true)->setSingleton(true),

        //展示
        (new Crontab())->setName('scheduleShowYesterday')->setRule('00 4 * * *')
            ->setCallback([ShowService::class, 'scheduleShowYesterday'])->setMemo('展示（昨天）')
            ->setOnOneServer(true)->setSingleton(true),
        (new Crontab())->setName('scheduleShowToday')->setRule('30 5-23 * * *')
            ->setCallback([ShowService::class, 'scheduleShowToday'])->setMemo('展示（当天）')
            ->setOnOneServer(true)->setSingleton(true),

        //设备数
        (new Crontab())->setName('scheduleDevice')->setRule('00 1 * * *')
            ->setCallback([DeviceService::class, 'scheduleDevice'])->setMemo('设备数')
            ->setOnOneServer(true)->setSingleton(true),
        (new Crontab())->setName('scheduleDeviceToday')->setRule('40 5-23 * * *')
            ->setCallback([DeviceService::class, 'scheduleDeviceToday'])->setMemo('设备数（当天）')
            ->setOnOneServer(true)->setSingleton(true),

        //投放自动检测计划任务
        (new Crontab())->setName('autoPlan')->setRule('*/5 * * * *')
            ->setCallback([AutoPlanService::class, 'schedulePlan'])->setMemo('投放自动检测计划任务')
            ->setOnOneServer(true)->setSingleton(true),

        //投放自动检测计划任务V2
        (new Crontab())->setName('autoPlanV2')->setRule('7,37 * * * *')
            ->setCallback([AutoPlanServiceV2::class, 'schedulePlan'])->setMemo('投放自动检测计划任务V2')
            ->setOnOneServer(true)->setSingleton(true),
        //投放自动检测应用任务V2
        (new Crontab())->setName('autoAppV2')->setRule('7,37 * * * *')
            ->setCallback([AutoAppServiceV2::class, 'schedulePlan'])->setMemo('投放自动检测应用任务V2')
            ->setOnOneServer(true)->setSingleton(true),

        // 用户行为ecpm分布
        (new Crontab())->setName('scheduleDeviceEcpmEvent')->setRule('10 00,06,12,18 * * *')
            ->setCallback([ProductService::class, 'scheduleDeviceEcpmEventHour'])->setMemo('用户行为ecpm分布')
            ->setOnOneServer(true)->setSingleton(true),

        // 报警规则检查 - 项目 每30分钟执行一次 (05,35)
        (new Crontab())->setType('command')->setName('mkt:alertCheck-project')->setRule('05,35 * * * *')
            ->setCallback([
                'command' => 'mkt:alertCheck',
                'type' => 'project'
            ])->setMemo('报警规则检查-项目')
            ->setOnOneServer(true)->setSingleton(true),
        // 报警规则检查 - 广告主 每30分钟执行一次 (13,43)
        (new Crontab())->setType('command')->setName('mkt:alertCheck-advertiser')->setRule('13,43 * * * *')
            ->setCallback([
                'command' => 'mkt:alertCheck',
                'type' => 'advertiser'
            ])->setMemo('报警规则检查-广告主')
            ->setOnOneServer(true)->setSingleton(true),
    ],
];
