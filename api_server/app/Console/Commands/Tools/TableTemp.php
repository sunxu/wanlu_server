<?php
// 将非热点数据移出热点区 凌晨执行
namespace App\Console\Commands\Tools;

use App\Services\Common\App;
use DB;
use Illuminate\Console\Command;
use Schema;

class TableTemp extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'table-temp {appcode?*}';

    /**
     * Command description
     * php artisan 202202081739 wifi smart
     * @var string
     */
    protected $description = '热点数据表处理';

    /**
     * 类型
     * @var array
     */
    protected $table = [
        // 表=>热点区保留数据天数
        'app_%s_event_ad_request' => [
            'field' => 'create_time',
            'days' => 1
        ],
        'app_%s_event_ad_full' => [
            'field' => 'create_time',
            'days' => 1
        ],
        'app_%s_event_ad_show' => [
            'field' => 'create_time',
            'days' => 1
        ],
        'app_%s_event_ad_click' => [
            'field' => 'create_time',
            'days' => 1
        ],
        'app_%s_event_ad_close' => [
            'field' => 'create_time',
            'days' => 1
        ],
        'app_%s_event_dialog' => [
            'field' => 'create_time',
            'days' => 1
        ],
        'app_%s_active' => [
            'field' => 'create_time',
            'days' => 1
        ],
        'app_%s_log' => [
            'field' => 'create_time',
            'days' => 1
        ],
        'app_%s_event_interval_group' => [
            'field' => 'start_time',
            'days' => 1
        ],
        'app_%s_event_ad_revenue' => [
            'field' => 'time',
            'format' => 'YmdHis',
            'days' => 1,
        ]
    ];

    /**
     * 非热点数据最长保留天数
     * @var int
     */
    protected $maxDay = 7;

    /**
     * 每次处理记录数
     * @var int
     */
    protected $limit = 50000;

    /**
     * 表格表头
     * @var array
     */
    protected $tableHeader = [
        '应用',
        '执行时间'
    ];

    /**
     * 表格内容
     * @var array
     */
    protected $tableBody = [];

    /**
     * Command Handle
     */
    public function handle()
    {
        $arguments = $this->arguments();

        // 保留最长时间
        $timestampMax = strtotime(date('Ymd', strtotime("-{$this->maxDay} days")));
        // 工具
        $apps = App::codeList([data_get(App::$appType, 'tool')], $arguments['appcode']);

        foreach ($apps as $code => $name) {
            if (!App::hasApp($code)) {
                continue;
            }
            $appStartTime = microtime(true);

            foreach ($this->table as $t => $d) {
                $days = $d['days'];
                $field = $d['field'];

                // 保留数据
                $maxTime = $timestampMax;
                $timestamp = strtotime(date('Ymd', strtotime("-{$days} days")));
                $table = sprintf($t, $code);

                if (isset($d['format'])) {
                    $timestamp = date($d['format'], $timestamp);
                    $maxTime = date($d['format'], $maxTime);
                }

                if (!Schema::hasTable($table)) {
                    continue;
                }

                $tempTable = sprintf('%s_temp', $table);

                if (!Schema::hasTable($tempTable)) {
                    DB::statement("
                        CREATE TABLE IF NOT EXISTS {$tempTable} LIKE {$table}
                    ");
                } else {
                    $num = $this->limit;
                    while ($num == $this->limit) {
                        $num = DB::affectingStatement("
                            DELETE FROM {$tempTable} WHERE {$field} < {$maxTime} LIMIT {$this->limit}
                        ");
                    }
                }

                $num = $this->limit;
                while ($num == $this->limit) {
                    DB::beginTransaction();

                    try {
                        DB::affectingStatement("
                            INSERT IGNORE INTO {$tempTable} SELECT * FROM {$table} WHERE {$field} < {$timestamp} ORDER BY {$field} LIMIT {$this->limit};
                        ");
                        $num = DB::delete("
                            DELETE FROM {$table} WHERE {$field} < {$timestamp} ORDER BY {$field} LIMIT {$this->limit}
                        ");
                    } catch (\Exception $exception) {
                        DB::rollBack();
                        $num = $this->limit;
                    }

                    DB::commit();
                }
            }

            $this->tableBody[] = [
                $code,
                sprintf('%.3fs', microtime(true) - $appStartTime)
            ];
        }

        $this->table($this->tableHeader, $this->tableBody);
    }
}
