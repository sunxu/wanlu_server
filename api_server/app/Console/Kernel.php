<?php
// 任务调度
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    /**
     * commands
     * @var array
     */
    protected $commands = [
        Commands\Test::class,
        Commands\TempCommand::class,

        // 工具
        Commands\Tools\Schedule::class,
        Commands\Tools\CreateApp::class,
        Commands\Tools\CreateAppNew::class,
        Commands\Tools\TableTemp::class,
        Commands\Tools\TableAdTemp::class,
        Commands\Tools\TableMarket::class,
        Commands\Tools\TencentClickTables::class,
        Commands\Tools\HonorClickTables::class,
        Commands\Tools\TableSplit::class,
        Commands\Tools\PlacementEcpm::class,//更新广告位价格
        Commands\Tools\RefreshApp::class,
        Commands\Tools\BlackList::class,

        // 打点
        Commands\Event\Insert::class,
        Commands\Event\Revenue::class,

        // 本地新统计
        Commands\Statistics\LocalDataAd::class,
        Commands\Statistics\LocalDataKeep::class,
        //回本率统计
        Commands\Statistics\LocalRecovery::class,
        //广告打点数据间隔统计
        Commands\Statistics\LocalDataRoi24::class,

        // 回收统计
        Commands\Revenue\RevenueHour::class,
        Commands\Revenue\RevenueDay::class,
    ];

    /**
     * command schedule.
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // 日期
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $beforeYesterday = date('Y-m-d', strtotime('-2 day'));
        $filePath = storage_path('logs/schedule-' . $today . '.log');

        // 表处理
        $schedule->command('table-temp')->dailyAt('00:30')->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production');
        $schedule->command('table-ad-temp')->dailyAt('01:35')->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production');
        $schedule->command('table-split')->weeklyOn(2, '0:05')->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production');
        $schedule->command('table-market')->daily()->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production');

        // 打点
        $schedule->command('event-insert')->everyMinute()->description('打点入库')->onOneServer()->runInBackground();
        $schedule->command('event-revenue')->cron("5,15,25,35,45,55 * * * *")->description('广告回收')->onOneServer()->runInBackground();

        // 新本地统计local-data-ad
//        $schedule->command('local-data-ad', [$today])->hourly()->description('统计：今日广告')->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production')->appendOutputTo($filePath);
        $schedule->command('local-data-ad', [$yesterday])->dailyAt('04:10')->description('统计：昨日广告')->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production');
        $schedule->command('local-data-keep')->dailyAt('04:10')->description('统计：昨日留存率')->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production');
        $schedule->command('local-data-keep', [$today])->hourlyAt(03)->description('统计：实时留存率')->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production');

        $schedule->command('local-data-roi24')->everyTenMinutes()->description('统计：roi24')->withoutOverlapping(10)->onOneServer()->runInBackground()->environments('production');
        $schedule->command('local-recovery')->dailyAt('04:10')->description('回本率统计')->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production');
//        $schedule->command('local-recovery', [$today])->hourlyAt([07, 37])->description('实时回本率统计')->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production');

        $schedule->command('revenue-hour', [0])->hourlyAt(45)->description('回收-小时')->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production');
        $schedule->command('revenue-day', [$today])->everyTenMinutes()->description('回收-当天')->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production');
        $schedule->command('revenue-day', [$yesterday])->dailyAt('02:20')->description('回收-昨天')->withoutOverlapping(30)->onOneServer()->runInBackground()->environments('production');

        $schedule->command('placement-ecpm')->cron("00 */2 * * *")->description('更新广告位价格')->onOneServer()->runInBackground()->environments('production');

        // 刷新APP缓存信息
        $schedule->command('refresh-app')->everyTenMinutes()->description('刷新APP缓存信息')->onOneServer()->runInBackground()->environments('production');
    }
}
