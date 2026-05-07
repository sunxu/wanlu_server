<?php
/**
 * 策略日志
 */

namespace App\Console\Commands\Log;

use App\Services\Common\App;
use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;
use Illuminate\Console\Command;

class LogStrategy extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'log-strategy';

    /**
     * Command description
     * @var string
     */
    protected $description = '策略日志';

    /**
     * 打点类型标识
     * @var string
     */
    protected $key = 'strategy';

    /**
     * 单次取队列最大数量
     * @var int
     */
    protected $maxNum = 10000;

    /**
     * Command Handle
     */
    public function handle()
    {
        // 工具
        $apps = App::codeList([data_get(App::$appType, 'tool')]);
        foreach ($apps as $appcode => $appname) {
            !in_array($appcode, ['ywq', 'hjjt', 'skqcl']) && $this->insert($appcode);
        }
    }

    /**
     * 策略入库
     * @param string $appcode
     */
    protected function insert(string $appcode)
    {
        if (empty($appcode)) {
            return;
        }
        $table = App::tableLogStrategy($appcode);
        $listKey = App::logListKey($this->key, $appcode);
        while (true) {
            $data = App::batchGetList($listKey, $this->maxNum);
            $this->doInsert($table, $data);
            if (count($data) < $this->maxNum) {
                return;
            }
        }

    }

    /**
     * 执行入库
     * @param $table
     * @param $data
     */
    public function doInsert($table, $data)
    {
        if (empty($data)) {
            return;
        }
        $insert = [];
        foreach ($data as $v) {
            $insert[] = json_decode($v, true);
        }
        try {
            DB::table($table)->insertOrIgnore($insert);
        } catch (Exception $e) {
            LogPush::error(__CLASS__ . ':' . __FUNCTION__, [
                'data' => $insert,
                'exception' => $e->getMessage()
            ]);
        }
    }
}
