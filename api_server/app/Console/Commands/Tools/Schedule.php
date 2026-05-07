<?php
/**
 * 任务调度列表
 */

namespace App\Console\Commands\Tools;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class Schedule extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'schedule-list';

    /**
     * Command description
     * @var string
     */
    protected $description = 'php artisan schedule-list';

    /**
     * Command Handle
     */
    public function handle()
    {
        // [1]加载Console内核
        app()->make(\Illuminate\Contracts\Console\Kernel::class);
        // [2]获取计划任务列表
        $scheduleList = app()->make(\Illuminate\Console\Scheduling\Schedule::class);
        // [3]遍历获取属性
        if (count($scheduleList->events()) > 0) {
            $tableList = collect($scheduleList->events())->map(function ($event) {
                if (!strpos($event->command, "artisan")) {
                    $command = $event->command;
                    $commandType = empty($command) ? 'job' : 'shell';
                } else {
                    $command = ltrim(strtok(Str::after($event->command, "artisan"), ''));
                    $commandType = 'command';
                }
                return [
                    'type' => $commandType,
                    'description' => $event->description,
                    'command' => $command,
                    'schedule' => $event->expression,
                    'upcoming' => $this->upcoming($event),
                    //'timezone' => $event->timezone ?: env('APP_TIMEZONE'),
                    'overlaps' => $event->withoutOverlapping ? 'No' : 'Yes',
                    'maintenance' => $event->evenInMaintenanceMode ? 'Yes' : 'No',
                    'background' => $event->runInBackground ? 'Yes' : 'No',
                    'one_server' => $event->onOneServer ? 'Yes' : 'No',
                ];
            })->toArray();
        }
        $this->table(
            [
                'Type',
                'Description',
                'Command',
                'Schedule',
                'Upcoming',
                //'Timezone',
                'Overlaps?',
                'In Maintenance?',
                'In Background?',
                'One Server?'],
            $tableList ?? []
        );
    }

    /**
     * 下次执行时间
     * @param $event
     * @return string
     */
    protected function upcoming($event)
    {
        $date = Carbon::now();

        if ($event->timezone) {
            $date->setTimezone($event->timezone);
        }

        return (CronExpression::factory($event->expression)->getNextRunDate($date->toDateTimeString()))->format('Y-m-d H:i:s');
    }
}