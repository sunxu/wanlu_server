<?php
/**
 * 打点
 */

namespace App\Http\Controllers\V1;

use App\Jobs\DeviceActiveJob;
use App\Services\Common\Account;
use App\Services\Common\App;
use App\Services\Common\Event;
use App\Services\Helper\Helper;
use Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

class EventController extends AppBaseController
{
    /**
     * 广告打点
     * @return JsonResponse
     */
    public function ad()
    {
        if (empty($this->params['androidid']) || empty($this->params['uuid']) || empty($this->params['event'])) {
            return Helper::response(1001);
        }
        // 过滤类型
        if (!in_array($this->params['event'], ['ad_request', 'ad_full', 'ad_show', 'ad_click', 'ad_close'])) {
            return Helper::response(0);
        }
        $info = $this->params['info'] ?? '[]';
        // 格式处理
        if (is_array($info)) {
            $info = json_encode($info, JSON_UNESCAPED_UNICODE);
        }
        $info = json_decode(str_replace("'", '', $info), true);
        if ($this->params['event'] == 'ad_request_failed' && isset($info['message'])) {
            $info['message'] = addslashes($info['message']);
        }

        if (in_array($this->params['event'], ['ad_show', 'ad_full', 'ad_request']) && $page = data_get($this->params, 'page')) {
            $page = explode(':', $page);
            $adType = array_pop($page);
            $info['adType'] = $adType ?? '';
        }

        if ($this->params['event'] == 'ad_show') {
            $biddingType = data_get($info, 'biddingType', 0);
            $placementId = data_get($info, 'networkPlacementId');
            $ecpm = data_get($info, 'ecpm', 0);
            if ($biddingType == 0 && $placementId) {
                $realEcpm = Cache::get('ecpm:' . $placementId);
                // ECPM取较小值
                if ($ecpm > 0 && $realEcpm > 0) {
                    $realEcpm = min($realEcpm, $ecpm);
                }
            }

            $info['realEcpm'] = round($realEcpm ?? $ecpm, 5);
        }

        $currTime = time();
        $data = [
            'version' => $this->version,
            'channel' => $this->params['channel'] ?? 0,
            'android_id' => $this->params['androidid'] ?? '',
            'vendor' => $this->params['vendor'] ?? '',
            'model' => $this->params['model'] ?? '',
            'create_time' => $currTime,
            'uuid' => $this->params['uuid'],
            'page' => $this->params['page'] ?? '',
            'info' => json_encode($info, JSON_UNESCAPED_UNICODE),
            'ad_time' => $this->params['ad_time'] ?? 0,
        ];

        if ($this->params['event'] == 'ad_show') {
            (new Event())->revenue($this->app['appcode'], $data);
        }

        // 激活定义
        if ($this->params['event'] == 'ad_show'
            && !empty($data['page'])
            && (in_array($data['page'], ['ad_splash', 'splash']) || strstr($data['page'], 'in_splash'))
        ) {
            $device = $this->getDevice();
            if (!empty($device['account_id']) && empty($device['active_time'])) {
                $account = Account::info($device['account_id']);
                // 开屏
                if (($account['active_type'] ?? 0) == 1) {
                    dispatch((new DeviceActiveJob([
                        'app_code' => $this->app['appcode'],
                        'device_id' => $data['android_id'],
                        'active_time' => $currTime,
                    ]))->onQueue('{device_active}'));
                }
            }
        }
        // 利用现有ad_close表处理存储ad_request_failed
        $event = $this->params['event'] == 'ad_request_failed' ? 'ad_close' : $this->params['event'];
        $result = Redis::rpush($this->eventListKey($event), json_encode($data, 320));

        return Helper::response($result ? 0 : 1000);
    }

    /**
     * 保活心跳
     * @return JsonResponse
     */
    public function active()
    {
        if (empty($this->params['androidid']) || empty($this->params['uuid'])) {
            return Helper::response(1001);
        }
        $data = [
            'version' => $this->version,
            'channel' => $this->params['channel'] ?? 0,
            'android_id' => $this->params['androidid'] ?? '',
            'vendor' => $this->params['vendor'] ?? '',
            'model' => $this->params['model'] ?? '',
            'os_version' => $this->version,
            'create_time' => time(),
            'time' => $this->params['time'] ?? 0,
            'uuid' => $this->params['uuid'],
        ];
        $result = Redis::rpush($this->eventListKey('active'), json_encode($data, 320));
        return Helper::response($result ? 0 : 1000);
    }


    /**
     * 外弹功能
     * @return JsonResponse
     */
    public function dialog()
    {
        if (empty($this->params['androidid']) || empty($this->params['uuid'])) {
            return Helper::response(1001);
        }
        $data = [
            'version' => $this->version,
            'channel' => $this->params['channel'] ?? 0,
            'android_id' => $this->params['androidid'] ?? '',
            'vendor' => $this->params['vendor'] ?? '',
            'model' => $this->params['model'] ?? '',
            'os_version' => $this->version,
            'create_time' => time(),
            'uuid' => $this->params['uuid'],
            'key_type' => $this->params['key_type'] ?? '',
            'req_type' => $this->params['req_type'] ?? '',
        ];
        $result = Redis::rpush($this->eventListKey('dialog'), json_encode($data, 320));
        return Helper::response($result ? 0 : 1000);
    }

    /**
     * 信息流
     * @return JsonResponse
     */
    public function information()
    {
        return Helper::response(0);
        if (empty($this->params['androidid']) || empty($this->params['uuid'])) {
            return Helper::response(1001);
        }
        $data = [
            'version' => $this->version,
            'channel' => $this->params['channel'] ?? 0,
            'android_id' => $this->params['androidid'] ?? '',
            'vendor' => $this->params['vendor'] ?? '',
            'model' => $this->params['model'] ?? '',
            'create_time' => time(),
            'uuid' => $this->params['uuid'],
            'type' => $this->params['type'] ?? '',
            'times' => $this->params['count'] ?? 1,
            'placement_id' => $this->params['adsourceid'] ?? '',
        ];
        $result = Redis::rpush($this->eventListKey('information'), json_encode($data, 320));
        return Helper::response($result ? 0 : 1000);
    }

    /**
     * 打点队列键
     * @param $key
     * @return string
     */
    private function eventListKey($key)
    {
        return App::eventListKey($key, $this->app['appcode']);
    }
}
