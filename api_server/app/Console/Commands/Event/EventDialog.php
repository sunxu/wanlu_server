<?php
/**
 * 外弹打点
 */

namespace App\Console\Commands\Event;

use App\Services\Common\App;
use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;
use Illuminate\Console\Command;

class EventDialog extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'event-dialog';

    /**
     * Command description
     * @var string
     */
    protected $description = '外弹打点批量入库';

    /**
     * 打点类型标识
     * @var string
     */
    protected $key = 'dialog';

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
            !in_array($appcode, ['ywq', 'hjjt', 'skqcl']) && $this->insert($appcode) ;
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
        $table = App::tableEventDialog($appcode);
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
        $insert = [];
        foreach ($data as $v) {
            $v = json_decode($v, true);
            if (!empty($v['uuid']) && !empty($v['key_type']) && !empty($v['req_type'])) {
                $insert[$v['uuid'] . $v['key_type'] . $v['req_type']] = $v;
            }
        }
        try {
            DB::table($table)->insertOrIgnore($insert);
        } catch (Exception $e) {
            LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
        }
    }
}
