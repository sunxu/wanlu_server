<?php
/**
 * 广告投放激活通知
 */

namespace App\Jobs;

use App\Services\Common\Account;
use App\Services\Common\App;
use App\Services\Helper\Helper;
use App\Services\Mkt\MktService;
use Cache;
use DB;
use Log;

class NotifyAdActiveJob extends BaseJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        echo json_encode($this->data, 320) . PHP_EOL;
        $device = DB::table(App::tableDevice($this->data['app_code']))
            ->useWritePdo()
            ->where('android_id', '=', $this->data['device_id'])
            ->first(['channel', 'vendor', 'os_version', 'source', 'ad_id', 'account_id', 'active_time',
                'imei',
                'oaid',
                'android_id',
            ]);

        if (empty($device['source']) || empty($device['ad_id']) || empty($device['active_time'])) {
            echo 'ad active' . PHP_EOL;
            $this->fail(json_encode($this->data, 320));
            return;
        }
        if ($device['source'] == 1) {
            $this->juliang($device);
        } elseif ($device['source'] == 2) {
            $this->kuaishou($device);
        } else {
            $this->service($device);
        }
    }

    /**
     * 巨量
     * @param $device
     */
    protected function juliang($device)
    {
        $table = App::tableAdJuliang($this->data['app_code']);
        // 激活回传
        $ad = DB::table($table)
            ->useWritePdo()
            ->where('id', $device['ad_id'])
            ->first(['id', 'account_id', 'active_status', 'callback_url', 'union_site', 'device_id']);
        if (empty($ad) || $ad['active_status'] != 1) {
            echo 'active_status != 1' . PHP_EOL;
            return;
        }
        // 扣量
        if ($this->discount($device, $table)) {
            return;
        }
        // 判断URL有效性
        if (!filter_var($ad['callback_url'], FILTER_VALIDATE_URL)) {
            echo 'invalidate callback_url' . PHP_EOL;
            return;
        }

        // 追加事件类型
        $callback_url = $ad['callback_url'];
        $postData = [];
        if (strpos($callback_url, 'analytics.oceanengine.com/api/v2/conversion') === false) {
            // old request
            $callback = sprintf('%s&event_type=%s&conv_time=%s&android_id=%s',
                $ad['callback_url'], 0, $device['active_time'],  $ad['device_id']);
            $result = Helper::request($callback);
        } else {
            $callback = sprintf('%s&event_type=%s&conv_time=%s', $ad['callback_url'], 0, $device['active_time']);

            $parsedUrl = parse_url($callback_url);
            $queryString = $parsedUrl['query'];
            parse_str($queryString, $params);
            $callback_param = $params['callback'];

            $postData = [
                "event_type" => "active",
                "context" => [
                    "ad" => [
                        "callback" => $callback_param,
                    ],
                    "device" => [
                        "platform" => "android",
                        "android_id" => $ad['device_id'],
                    ]
                ],
                "timestamp" => time(),
            ];
            $header = array(
                'Content-Type' => 'application/json',
            );
            $result = Helper::request($callback, "POST", json_encode($postData), $header);
        }

        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'data' => $this->data,
            'callback' => $callback,
            'postData' => json_encode($postData),
            'result' => $result
        ]);
        if (isset($result['http_code']) && $result['http_code'] == 200 && !empty($result['result'])) {
            $data = json_decode($result['result'], true);
            if (isset($data['code']) && $data['code'] == 0) {
                $this->notifySuccess($table, $ad['id']);
                return;
            }
        }
        $this->notifyFail($table, $ad['id']);
    }

    /**
     * 快手
     * @param $device
     */
    protected function kuaishou($device)
    {
        $table = App::tableAdKuaishou($this->data['app_code']);
        // 激活回传
        $ad = DB::table($table)
            ->useWritePdo()
            ->where('id', $device['ad_id'])
            ->first(['id', 'account_id', 'active_status', 'callback_url']);
        if (empty($ad) || $ad['active_status'] != 1) {
            echo 'active_status != 1' . PHP_EOL;
            return;
        }
        // 扣量
        if ($this->discount($device, $table)) {
            return;
        }
        // 判断URL有效性
        if (!filter_var($ad['callback_url'], FILTER_VALIDATE_URL)) {
            echo 'invalidate callback_url' . PHP_EOL;
            return;
        }
        // 追加事件类型
        $callback = sprintf('%s&event_type=%s&event_time=%s', $ad['callback_url'], 1, $device['active_time'] * 1000);
        $result = Helper::request($callback);
        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'data' => $this->data,
            'callback' => $callback,
            'result' => $result
        ]);
        if (!empty($result['result'])) {
            $data = json_decode($result['result'], true);
            if (($data['result'] ?? -1) === 1) {
                $this->notifySuccess($table, $ad['id']);
                return;
            }
        }

        $this->notifyFail($table, $ad['id']);
    }

    protected function service($device)
    {
        $table = App::tableAdBySource($this->data['app_code'], $device['source']);

        // 激活回传
        $ad = DB::table($table)
            ->where('id', $device['ad_id'])
            ->first();

        if (empty($ad) || $ad['active_status'] != 1) {
            echo 'active_status != 1' . PHP_EOL;
            return;
        }
        // 扣量
        if ($this->discount($device, $table)) {
            return;
        }

        $ad['app_code'] = $this->data['app_code'];
        try {
            $result = (new MktService($device['source']))->callback('activate', $ad['active_time'], (array)$ad);
        } catch (\Exception $e) {
            $result = false;
        }

        $result ? $this->notifySuccess($table, $ad['id']) : $this->notifyFail($table, $ad['id']);
    }

    /**
     * 成功
     * @param $table
     * @param $id
     */
    protected function notifySuccess($table, $id)
    {
        DB::table($table)
            ->where('id', $id)
            ->where("active_status", 1)
            ->update([
                'update_time' => time(),
                "active_status" => 2
            ]);
        echo 'success' . PHP_EOL;
    }

    /**
     * 失败
     * @param $table
     * @param $id
     */
    protected function notifyFail($table, $id)
    {
        DB::table($table)
            ->where('id', $id)
            ->where("active_status", 1)
            ->update([
                'update_time' => time(),
                "active_times" => DB::raw("active_times + 1")
            ]);
        if ($this->attempts() < $this->tries) {
            $this->release(10 * $this->attempts());
        }
        echo 'fail' . PHP_EOL;
    }

    /**
     * 扣量
     * @param $device
     * @param $table
     * @return bool
     */
    private function discount($device, $table)
    {
        if ($this->common($device)) {
            DB::table($table)
                ->where('id', $device['ad_id'])
                ->where('active_status', 1)
                ->update([
                    'active_times' => 99,
                    'update_time' => time()
                ]);
            echo 'discount-common' . PHP_EOL;
            return true;
        }
        
        $extra = App::extra($this->data['app_code']);
        if (in_array($device['channel'], $extra['block_channel'] ?? [])) {
            DB::table($table)
                ->where('id', $device['ad_id'])
                ->where('active_status', 1)
                ->update([
                    'active_times' => 66,
                    'update_time' => time()
                ]);
            echo 'discount-block_channel' . PHP_EOL;
            return true;
        }
        return false;
    }

    /**
     * 大于等于安卓11系统
     * @param $device
     * @return bool
     */
    protected function osgt11($device)
    {
        return !empty($device['os_version']) && Helper::compareVersion($device['os_version'], '11.0') >= 0;
    }

    /**
     * 大于等于安卓12系统
     * @param $device
     * @return bool
     */
    protected function osgt12($device)
    {
        return !empty($device['os_version']) && Helper::compareVersion($device['os_version'], '12.0') >= 0;
    }

    /**
     * 小于安卓8系统
     * @param $device
     * @return bool
     */
    protected function oslt8($device)
    {
        return !empty($device['os_version']) && Helper::compareVersion($device['os_version'], '8.0') < 0;
    }

    /**
     * 关键行为账户
     * @param $device
     * @return bool
     */
    protected function keyActionAccount($device)
    {
        return Account::isKeyAction($device['account_id']);
    }

    /**
     * 华为
     * @param $device
     * @return bool
     */
    protected function IsHuawei($device)
    {
        return in_array($device['vendor'], ['HUAWEI', 'HONOR']);
    }

    /**
     * 小米
     * @param $device
     * @return bool
     */
    protected function IsXiaomi($device)
    {
        return $device['vendor'] == 'Xiaomi';
    }

    /**
     * oppo
     * @param $device
     * @return bool
     */
    protected function IsOppo($device)
    {
        return $device['vendor'] == 'OPPO';
    }

    /**
     * vivo
     * @param $device
     * @return bool
     */
    protected function IsVivo($device)
    {
        return $device['vendor'] == 'vivo';
    }

    /**
     * 主流厂商
     * @param $device
     * @return bool
     */
    protected function mainVendor($device)
    {
        return $this->IsHuawei($device) || $this->IsXiaomi($device) || $this->IsOppo($device) || $this->Isvivo($device);
    }

    /**
     * 通用扣量
     * @param $device
     * @return bool
     */
    protected function common($device)
    {
        return $this->oslt8($device);
//        return $this->oslt8($device)
//            ||
//            // 非关键行为帐户屏蔽安卓12
//            ($this->osgt12($device) && !$this->keyActionAccount($device));
    }
}
