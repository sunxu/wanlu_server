<?php
// 移除投放点击数据
namespace App\Console\Commands\Tools;

use App\Services\Common\App;
use DB;
use Illuminate\Console\Command;
use Schema;

class TableMarket extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'table-market {appcode?*}';

    /**
     * Command description
     * @var string
     */
    protected $description = '移除投放点击数据';

    /**
     * 类型
     * @var array
     */
    protected $table = [
        'app_%s_ad',
        'app_%s_ks',
        'app_%s_oppo',
        'app_%s_huawei',
        'app_%s_vivo',
        'app_%s_bd',
        'app_%s_tx',
    ];

    /**
     * 未匹配保留天数
     * @var int
     */
    protected $maxDay = 3;

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
        // 保留数据
        $timestamp = strtotime(date('Ymd', strtotime("-{$this->maxDay} days")));

        // 工具
        $apps = App::codeList([data_get(App::$appType, 'tool')], $arguments['appcode']);

        foreach ($apps as $code => $name) {
            if (!App::hasApp($code)) {
                continue;
            }
            $appStartTime = microtime(true);
            foreach ($this->table as $t) {
                $table = sprintf($t, $code);
                if (!Schema::hasTable($table)) {
                    continue;
                }
                $num = $this->limit;
                while ($num == $this->limit) {
                    $num = DB::affectingStatement("
                        DELETE FROM {$table} WHERE ts < {$timestamp} AND device_id = '' ORDER BY ts LIMIT {$this->limit};
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
