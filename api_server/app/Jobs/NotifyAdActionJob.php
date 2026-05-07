<?php
/**
 * 广告投放关键行为通知
 */

namespace App\Jobs;

use App\Services\Common\App;
use App\Services\Helper\Helper;
use App\Services\Mkt\MktService;
use DB;
use Log;

class NotifyAdActionJob extends BaseJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        echo json_encode($this->data, 320) . PHP_EOL;

        if ($this->data['source'] == 1) {
            $this->juliang();
        } elseif ($this->data['source'] == 2) {
            $this->kuaishou();
        } else {
            $this->service();
        }
    }

    /**
     * 巨量
     */
    protected function juliang()
    {
        $table = App::tableAdJuliang($this->data['app_code']);
        // 激活回传
        $ad = DB::table($table)
            ->where('id', $this->data['ad_id'])
            ->first(['id', 'active_status', 'callback_url', 'device_id']);
        if (empty($ad) || $ad['active_status'] != 2) {
            echo 'active_status != 2' . PHP_EOL;
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
            $callback = sprintf('%s&event_type=%s&conv_time=%s&android_id=%s', $ad['callback_url'], 25, $this->data['action_time'], $ad['device_id']);
            $result = Helper::request($callback);
        } else {
            $callback = sprintf('%s&event_type=%s&conv_time=%s', $ad['callback_url'], 25, $this->data['active_time']);

            $parsedUrl = parse_url($callback_url);
            $queryString = $parsedUrl['query'];
            parse_str($queryString, $params);
            $callback_param = $params['callback'];

            $postData = [
                "event_type" => "game_addiction",
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
     */
    protected function kuaishou()
    {
        $table = App::tableAdKuaishou($this->data['app_code']);
        // 激活回传
        $ad = DB::table($table)
            ->where('id', $this->data['ad_id'])
            ->first(['id', 'active_status', 'callback_url']);
        if (empty($ad) || $ad['active_status'] != 2) {
            return;
        }
        // 判断URL有效性
        if (!filter_var($ad['callback_url'], FILTER_VALIDATE_URL)) {
            return;
        }
        // 追加事件类型
        $callback = sprintf('%s&event_type=%s&event_time=%s', $ad['callback_url'], 143, $this->data['action_time'] * 1000);
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

    protected function service()
    {
        $table = App::tableAdBySource($this->data['app_code'], $this->data['source']);
        // 激活回传
        $ad = DB::table($table)
            ->where('id', $this->data['ad_id'])
            ->first();

        if (empty($ad) || $ad['active_status'] != 2 || $ad['action_status'] != 1) {
            echo 'active_status' . PHP_EOL;
            return;
        }
        $ad['app_code'] = $this->data['app_code'];
        try {
            $result = (new MktService($this->data['source']))->callback('key_action', $ad['action_time'], (array)$ad);
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
            ->where("action_status", 1)
            ->update([
                'update_time' => time(),
                "action_status" => 2
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
            ->where("action_status", 1)
            ->update([
                'update_time' => time(),
                "action_times" => DB::raw("action_times + 1")
            ]);
        if ($this->attempts() < $this->tries) {
            $this->release(10 * $this->attempts());
        }
        echo 'fail' . PHP_EOL;
    }
}
