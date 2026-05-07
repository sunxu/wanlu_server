<?php
/**
 * 设备日志
 */

namespace App\Console\Commands\Log;

use App\Services\Common\App;
use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;
use Illuminate\Console\Command;

class LogDeviceDate extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'log-device-date';

    /**
     * Command description
     * @var string
     */
    protected $description = '设备日志某天';

    /**
     * 打点类型标识
     * @var string
     */
    protected $key = 'device-date';

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
            $this->insert($appcode);
        }
    }

    /**
     * 设备留存记录
     * @param string $appcode
     */
    protected function insert(string $appcode)
    {
        if (empty($appcode)) {
            return;
        }
        $table = App::tableLogDevice($appcode);
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
            $v = json_decode($v, true);
            if (!empty($v['device_id']) && !empty($v['date'])) {
                $insert[$v['date'] . $v['device_id']] = $v;
            }
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
