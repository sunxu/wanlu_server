<?php
// 投放数据保留周期调整
namespace App\Console\Commands\Tools;

use App\Services\Common\App;
use DB;
use Illuminate\Console\Command;
use Schema;

class TableAdTemp extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'table-ad-temp {appcode?*}';

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
        // 控制时间字段
        'app_%s' => 'create_time',
        'app_%s_active_keep' => 'install_day',
        'app_%s_ad' => 'ts',
        'app_%s_ks' => 'ts',
        'app_%s_oppo' => 'ts',
        'app_%s_huawei' => 'ts',
        'app_%s_vivo' => 'ts',
    ];

    /**
     * 非热点数据最长保留天数
     * @var int
     */
    protected $maxDay = 31;

    /**
     * 每次处理记录数
     * @var int
     */
    protected $limit = 10000;

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

            $appStartTime = microtime(true);

            foreach ($this->table as $t => $d) {

                switch ($d) {
                    case 'install_day':
                        $dValue = date('Ymd', $timestampMax);
                        break;
                    case 'date':
                        $dValue = "'" . date('Y-m-d', $timestampMax) . "'";
                        break;
                    default:
                        $dValue = $timestampMax;
                }

                $table = sprintf($t, $code);

                if (!Schema::hasTable($table)) {
                    continue;
                }

                $tempTable = sprintf('%s_temp', $table);

                if (!Schema::hasTable($tempTable)) {
                    DB::statement("
                        CREATE TABLE IF NOT EXISTS {$tempTable} LIKE {$table}
                    ");
                }

                $num = $this->limit;
                while ($num == $this->limit) {
                    DB::affectingStatement("
                        INSERT IGNORE INTO {$tempTable} SELECT * FROM {$table} WHERE {$d} < {$dValue} ORDER BY {$d} LIMIT {$this->limit};
                    ");
                    $num = DB::delete("
                        DELETE FROM {$table} WHERE {$d} < {$dValue} ORDER BY {$d} LIMIT {$this->limit}
                    ");
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
