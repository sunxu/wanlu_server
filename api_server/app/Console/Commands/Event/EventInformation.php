<?php
/**
 * 信息流打点
 */

namespace App\Console\Commands\Event;

use App\Services\Common\App;
use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;
use Illuminate\Console\Command;

class EventInformation extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'event-information';

    /**
     * Command description
     * @var string
     */
    protected $description = '信息流打点批量入库';

    /**
     * 打点类型标识
     * @var string
     */
    protected $key = 'information';

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
            $this->insert($appcode);
        }
    }

    /**
     * 外弹打点
     * @param string $appcode
     */
    protected function insert(string $appcode)
    {
        if (empty($appcode)) {
            return;
        }
        $table = App::tableEventInformation($appcode);
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
            $sql .= "('{$value}'),";
        }
        $sql = substr($sql, 0, -1);
        $sql .= " ON DUPLICATE KEY UPDATE
                    times = times + VALUES(times),
                    update_time = VALUES(create_time)";
        try {
            DB::insert($sql);
        } catch (Exception $e) {
            LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
        }
    }
}
