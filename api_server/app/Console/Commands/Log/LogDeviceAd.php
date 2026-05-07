<?php
/**
 * 设备广告日志
 */

namespace App\Console\Commands\Log;

use App\Services\Common\App;
use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;
use Illuminate\Console\Command;

class LogDeviceAd extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'log-device-ad';

    /**
     * Command description
     * @var string
     */
    protected $description = '设备日志';

    /**
     * 间隔时间（分钟）
     * @var int
     */
    protected $intervalTime = 10;

    /**
     * 表格表头
     * @var array
     */
    protected $tableHeader = [
        '应用',
        '数量',
        '执行时间'
    ];

    /**
     * 表格内容
     * @var array
     */
    protected $tableBody = [];

    /**
     * 当前时间戳
     * @var
     */
    protected $nowTime;

    /**
     * Command Handle
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->nowTime = time();
        // 工具
        $apps = App::codeList([data_get(App::$appType, 'tool')]);
        foreach ($apps as $appcode => $appname) {
            $this->adTime($appcode);
        }
        $this->table($this->tableHeader, $this->tableBody);
        $this->table(['总执行时间'], [[sprintf('%.3fs', microtime(true) - $startTime)]]);
    }

    /**
     * 用户广告时间
     * @param string $appcode
     */
    protected function adTime(string $appcode)
    {
        if (empty($appcode)) {
            return;
        }
        $appStartTime = microtime(true);
        $tableAd = App::tableEventAdShow($appcode);
        $tableLog = App::tableLogDevice($appcode);
        // 打点的设备信息
        $deviceAd = DB::table($tableAd)
            ->where('create_time', '>=', $this->nowTime - $this->intervalTime * 60)
            ->groupBy('android_id')
            ->pluck('create_time', 'android_id')
            ->toArray();

//        $deviceIds = implode(',', array_keys($deviceAd));
//        $sql = "UPDATE {$tableLog} SET ad_time = CASE device_id ";
//        foreach ($deviceAd as $device => $time) {
//            $sql .= sprintf(" WHEN %s THEN %s", $deviceAd, $time);
//        }
//        $sql .= "END WHERE date = {$this->date} AND device_id IN ({$deviceIds})";

        foreach ($deviceAd as $device => $time) {
            try {
                DB::table($tableLog)
                    ->where('date', date('Y-m-d', $time))
                    ->where('device_id', $device)
                    ->where('ad_time', 0)
                    ->update([
                        'ad_time' => $time,
                        'update_time' => $this->nowTime,
                    ]);
            } catch (Exception $e) {
                LogPush::error(__CLASS__ . ':' . __FUNCTION__, [
                    'data' => [
                        'ad_time' => $time,
                        'android_id' => $device,
                    ], 'exception' => $e->getMessage()]);
            }
        }
        $this->tableBody[] = [
            $appcode,
            count($deviceAd),
            sprintf('%.3fs', microtime(true) - $appStartTime)
        ];
    }
}
