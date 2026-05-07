<?php
/**
 * 保活打点
 */

namespace App\Console\Commands\Event;

use App\Services\Common\App;
use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;
use Illuminate\Console\Command;

class EventActive extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'event-active';

    /**
     * Command description
     * @var string
     */
    protected $description = '保活打点批量入库';

    /**
     * 打点类型标识
     * @var string
     */
    protected $key = 'active';

    /**
     * 单次取队列最大数量
     * @var int
     */
    protected $maxNum = 5000;

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
     * 保活打点
     * @param string $appcode
     */
    protected function insert(string $appcode)
    {
        if (empty($appcode)) {
            return;
        }
        $table = App::tableEventActive($appcode);
        $listKey = App::eventListKey($this->key, $appcode);

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
        $values = json_decode(reset($data), true);
        $keys = implode(',', array_keys($values));
        $sql = "INSERT INTO {$table} ( {$keys} ) VALUES ";
        foreach ($data as $v) {
            $value = implode("','", json_decode($v, true));
            $sql .= "( '{$value}' ),";
        }
        $sql = substr($sql, 0, -1);
        $sql .= " ON DUPLICATE KEY UPDATE 
                        time = VALUES(time), 
                        update_time = VALUES(create_time)";
        try {
            DB::insert($sql);
        } catch (Exception $e) {
            LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
        }
    }
}
