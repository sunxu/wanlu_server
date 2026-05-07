<?php

namespace App\Services\JlMarket;

use App\Constants\DeviceConst;
use App\Services\Common\App;
use App\Services\Helper\Helper;
use App\Services\Market\KeepInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KeepService implements KeepInterface
{
    /**
     * app code
     *
     * @var string
     */
    protected $appCode;

    /**
     * 设备 ID
     *
     * @var string
     */
    protected $androidId;

    protected $adId;

    /**
     * 设备信息
     *
     * @var array
     */
    protected $device;

    /**
     * 长留存类型
     *
     * @var int[]
     */
    protected $longKeepType = [
        2 => 222,
        3 => 223,
        4 => 224,
        5 => 225,
        6 => 226,
        7 => 227,
    ];

    /**
     * 留存
     *
     * @param string $appCode
     * @param array $params
     * @return void
     */
    public function doKeep(string $appCode, array $params): void
    {
        $this->appCode = $appCode;
        $this->androidId = data_get($params, 'android_id');
        $this->device = DB::table(App::tableDevice($appCode))->where('android_id', $this->androidId)->first(['id', 'ad_id', 'active_time', 'more_keep']);
        $this->longKeep($params);
    }

    /**
     * 时间段留存
     *
     * @param array $params
     * @param int $start
     * @param int $end
     * @return void
     */
    protected function longKeep(array $params, int $start = 2, int $end = 7): void
    {
        $deviceAd = DB::table(App::tableAdJuliang($this->appCode))
            ->where('id', data_get($this->device, 'ad_id', 0))
            ->first(['id', 'active_status', 'callback_url']);
        if (
            !$deviceAd ||
            data_get($deviceAd, 'active_status') != DeviceConst::ACTIVE_STATUS['on_notify']
        ){
            return;
        }
        $activeTime = date("Y-m-d 00:00:00", data_get($this->device, 'active_time'));
        $keepDay = Carbon::createFromFormat('Y-m-d H:i:s', $activeTime)->diffInDays(date("Y-m-d 00:00:00", data_get($params, 'create_time')));
        //是否已经提交次留
        if (
            $keepDay < $start ||
            $keepDay > $end ||
            Helper::getMoreKeep($keepDay, data_get($this->device, 'more_keep')) ||
            !isset($this->longKeepType[$keepDay])
        ){
            return;
        }
        // 判断URL有效性
        if (!filter_var(data_get($deviceAd, 'callback_url', ''), FILTER_VALIDATE_URL)) {
            echo 'invalidate callback_url' . PHP_EOL;
            return;
        }
        $callbackUrl = sprintf('%s&event_type=%s&conv_time=%s', data_get($deviceAd, 'callback_url'), $this->longKeepType[$keepDay], data_get($params, 'create_time'));
        $result = Helper::request($callbackUrl);
        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'callback' => $callbackUrl,
            'result' => $result
        ]);
        if (data_get($result, 'http_code') != 200 || !data_get($result, 'result')) {
            return;
        }
        $data = json_decode($result['result'], true);
        if (data_get($data, 'code') !== 0) {
            return;
        }
        DB::table(App::tableDevice($this->appCode))
            ->where('id', data_get($this->device, 'id'))
            ->update([
                'update_time' => time(),
                "more_keep" => Helper::setMoreKeep($keepDay, data_get($this->device, 'more_keep'))
            ]);
    }
}