<?php
/**
 * 重命名表存储增量数据|针对偶尔需要查询的表
 */

namespace App\Console\Commands\Tools;

use App\Services\Common\App;
use DB;
use Illuminate\Console\Command;
use Schema;

class TableSplit extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'table-split {appcode?*}';

    /**
     * Command description
     * @var string
     */
    protected $description = '拆分表';

    /**
     * 表
     * @var array
     */
    protected $table = [
//        'app_%s_event_dialog',
        'app_%s_event_information',
        'app_%s_switch_log'
    ];

    /**
     * 表最大记录限制
     * @var int
     */
    protected $maxNum = 1000000;

    /**
     * 表格表头
     * @var array
     */
    protected $tableHeader = [
        '应用',
        '表名',
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

        $date = date('Ymd');
        // 工具
        $apps = App::codeList([data_get(App::$appType, 'tool')], $arguments['appcode']);

        foreach ($apps as $code => $name) {
            if (!App::hasApp($code)) {
                continue;
            }
            $appStartTime = microtime(true);
            $arr = [];

            foreach ($this->table as $t) {
                $table = sprintf($t, $code);
                $tableBack = sprintf('%s_%s', $table, $date);

                if (!Schema::hasTable($table) || DB::table($table)->count() < $this->maxNum) {
                    continue;
                }

                Schema::rename($table, $tableBack);

                DB::statement("
                    CREATE TABLE IF NOT EXISTS {$table} LIKE {$tableBack}
                ");

                $arr[] = $t;
            }

            $this->tableBody[] = [
                $code,
                implode(',', $arr),
                sprintf('%.3fs', microtime(true) - $appStartTime)
            ];
        }

        $this->table($this->tableHeader, $this->tableBody);
    }
}
