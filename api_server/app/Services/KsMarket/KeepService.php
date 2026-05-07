<?php

namespace App\Services\KsMarket;

use App\Constants\DeviceConst;
use App\Constants\MarketConst;
use App\Services\Common\App;
use App\Services\Helper\Helper;
use App\Services\Market\KeepInterface;
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

    /**
     * 设备信息
     *
     * @var array
     */
    protected $device;

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
        $this->device = DB::table(App::tableDevice($appCode))->where('android_id', $this->androidId)->first(['id', 'hour_keep', 'ad_id', 'active_time']);
        $this->betweenHourKeep($params);
    }

    /**
     * 24小时留存
     *
     * @param array $params
     * @return void
     */
    protected function betweenHourKeep(array $params): void
    {
        $deviceAd = DB::table(App::tableAdKuaishou($this->appCode))
            ->where('id', data_get($this->device, 'ad_id', 0))
            ->first(['id', 'active_status', 'callback_url']);

        if (
            !$deviceAd ||
            data_get($deviceAd, 'active_status') != DeviceConst::ACTIVE_STATUS['on_notify'] ||
            data_get($this->device, 'hour_keep') == MarketConst::HOUR_KEEP['keep']
        ){
            return;
        }

        // 判断URL有效性
        if (!filter_var(data_get($deviceAd, 'callback_url', ''), FILTER_VALIDATE_URL)) {
            echo 'invalidate callback_url' . PHP_EOL;
            return;
        }
        $callbackUrl = sprintf('%s&event_type=%s&event_time=%s', data_get($deviceAd, 'callback_url'), MarketConst::KS_EVENT_TYPE['hour_keep'], data_get($params, 'create_time') * 1000);
        $result = Helper::request($callbackUrl);
        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'callback' => $callbackUrl,
            'result' => $result
        ]);
        if (!data_get($result, 'result')){
            return;
        }
        $data = json_decode($result['result'], true);
        if (($data['result'] ?? -1) === 1) {
            DB::table(App::tableDevice($this->appCode))
                ->where('id', data_get($this->device, 'id'))
                ->update([
                    'update_time' => time(),
                    "hour_keep" => MarketConst::HOUR_KEEP['keep']
                ]);
        }
    }
}
