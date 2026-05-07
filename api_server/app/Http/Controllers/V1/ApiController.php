<?php
/**
 * 广告策略
 */

namespace App\Http\Controllers\V1;

use App\Constants\AdConst;
use App\Lib\Volcengine\Risk;
use App\Services\Common\Account;
use App\Services\Common\App;
use App\Services\Helper\Helper;
use App\Services\Helper\Tools\Dding;
use App\Services\Mkt\GuiyinService;
use Cache;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Schema;

class ApiController extends AppBaseController
{
    /**
     * 配置信息
     * @var array
     */
    protected $apiConfig;

    /**
     * 投放渠道|[1]巨量[2]快手
     * @var int
     */
    protected $source = 1;

    /**
     * 投放账户
     * @var string
     */
    protected $account = '';

    /**
     * 是否老用户
     * @var bool
     */
    protected $old = false;

    /**
     * 归因成功标记
     * @var bool
     */
    protected $adSuccess = false;

    // 设备信息
    protected $device = null;

    /**
     * 云控配置
     * @param Request $request
     * @return JsonResponse
     */
    public function config(Request $request)
    {
        // API配置信息
        $this->setApiConfig();

        $this->device = $this->device ?? $this->getDevice();
        // 策略判断
        switch (true) {
            // 指定策略|
            case $this->clientAudit():
                $strategyKey = 'audit';
                $target = 'client';
                $this->insertBlack([
                    'client_audit' => true
                ]);
                break;
            case $this->location():
                $strategyKey = 'audit';
                $target = 'location';
                break;
            // 指定正常策略渠道
            case $this->isCommonChannel():
                $strategyKey = 'common';
                $target = 'common_channel';
                break;
            // 推广用户
            case $this->isPromotionAd():
                $strategyKey = 'common';
                $target = 'promotion_ad';
                // 落地页过滤黑名单
                if (($this->isLanding() && $this->landingBlacklist())
                    ||
//                    ($this->isAudit() || $this->isBlacklist(true))
                    ($this->adSuccess && ($this->isAudit() || $this->isBlacklist(true)))
                ) {
                    // 正常策略
                    $strategyKey = 'audit';
                    // 命中标识
                    $target = 'promotion_ad_black';
                }
                // 归因超30天老用户
                if ($this->old) {
                    $target = 'promotion_ad_old';
                }

                break;
            // 白名单
            case App::isProduct() && $this->isWhitelist():
                $strategyKey = 'common';
                $target = 'white';
                break;
            // 黑名单
            case App::isProduct() && !$this->isLanding() && $this->isBlacklist():
                $strategyKey = 'audit';
                $target = 'black';
                break;
            // 审核状态
            case $this->isAudit():
                // 落地页收集审核黑名单
                if ($this->isLanding()) {
                    $this->insertLandingBlack();
                }
                $strategyKey = 'audit';
                $target = 'audit';
                break;
//            case $this->installTime():
//                $strategyKey = 'common';
//                $target = 'install_time';
//                break;
//            // 低版本用户
//            case $this->lowerVersionUser():
//                $strategyKey = 'common';
//                $target = 'lower_version';
//                break;
            default:
                // 巡检策略
                $strategyKey = 'patrol';
                $target = 'default';
        }

        // 风险识别
//        if ($strategyKey == 'common' && !empty($this->app['volc_appid']) && !empty($this->params['bd_did']) && $this->risk()) {
//            $strategyKey = 'audit';
//            // 命中标识
//            $target = 'risk';
//        }

        // 策略
        $versionConfig = $this->getStrategy($strategyKey);
        // 策略信息
        $strategyInfo = [
            'target' => $target,
            'key' => $strategyKey,// 客户端判断策略依据
            'channel' => $this->channel,
            'user_source' => $this->source,
            'strategy_source' => $versionConfig['source'] ?? 0,
            'strategy_id' => $versionConfig['strategy_id'] ?? 0,
            'launch_type' => $versionConfig['launch_type'] ?? '',
        ];
        unset($versionConfig['source'], $versionConfig['strategy_id'], $versionConfig['launch_type']);

        // 策略日志
        $this->logStrategy($strategyInfo);
        // 应用统计
        $this->appList();

        return Helper::response(0, array_merge($versionConfig, [
            'strategy' => $strategyInfo,
        ]));
    }

    /**
     * 是否推广广告用户
     * @return bool
     * @throws \Exception
     */
    protected function isPromotionAd()
    {
        $device = $this->device ?? $this->getDevice();

        if (!empty($device) && ($device['ad_time'] ?? 0) > 0) {
            if ($device['source'] > 0) {
                $this->source = $device['source'];
                $this->account = $device['account_id'];
            }
            return true;
        }

        // 快应用归因
        $device['req_id'] = $this->params['req_id'] ?? '';
        // 归因成功
        if ($ad = GuiyinService::handle($this->app['appcode'], $device)) {
            $this->adSuccess = true;
            $this->source = $ad['source'];
            $this->account = $ad['account_id'];
            return true;
        }

        // 历史老用户
        $oldUser = $this->oldUser();
        if (!empty($oldUser) && ($oldUser['ad_time'] ?? 0) > 0) {
            if ($oldUser['source'] > 0) {
                $this->source = $oldUser['source'];
                $this->account = $oldUser['account_id'];
            }
            $this->old = true;
            return true;
        }

        return false;
    }

    /**
     * 指定渠道用户
     * @return bool
     */
    protected function isCommonChannel()
    {
        return in_array($this->channel, [5, 14, 1001, 1002]);
    }

    /**
     * 是否白名单用户
     * @return bool
     */
    protected function isWhitelist()
    {
        if (!App::isProduct()) {
            return false;
        }
        $key = 'whitelist';
        $data = Cache::get($key, []);
        if (empty($data)) {
            $list = DB::table('com_whitelist')
                ->get()
                ->toArray();
            $data = [];
            foreach ($list as $v) {
                if (!empty($v['oaid'])) {
                    $data['oaid'][$v['oaid']] = $v['id'];
                }
                if (!empty($v['android_id'])) {
                    $data['android_id'][$v['android_id']] = $v['id'];
                }
                if (!empty($v['imei'])) {
                    $data['imei'][$v['imei']] = $v['id'];
                }
                if (!empty($v['mac'])) {
                    $data['mac'][$v['mac']] = $v['id'];
                }
            }
            Cache::put($key, $data, 24 * 3600);
        }
        $arr = [
            'imei' => 'imei',
            'android_id' => 'android_id',
            'oaid' => 'oaid',
            'mac' => 'dmac',
        ];
        foreach ($arr as $k => $v) {
            if (!empty($this->params[$v]) && isset($data[$k][$this->params[$v]])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 是否审核状态
     * @return bool
     */
    protected function isAudit()
    {
        return false;
    }

    /**
     * 策略日志
     * @param array $strategy
     * @return bool
     */
    protected function logStrategy(array $strategy)
    {
        $strategy_info = [
            'id' => $strategy['strategy_id'] ?? 0,
            'source' => $strategy['strategy_source'] ?? '',
            'launch_type' => $strategy['launch_type'] ?? '',
        ];
        $data = [
            'version' => $this->version,
            'req_type' => $this->params['from'] ?? '',
            'channel' => $this->channel,
            'imei' => json_encode($strategy_info),
            'oaid' => $this->params['oaid'] ?? '',
            'android_id' => $this->params['androidid'] ?? '',
            'vendor' => $this->params['vendor'] ?? '',
            'model' => $this->params['model'] ?? '',
            'strategy_version' => $strategy['version'] ?? '',
            'strategy_channel' => $strategy['channel'] ?? '',
            'strategy_key' => $strategy['key'] ?? '',
            'strategy_target' => $strategy['target'] ?? '',
            'create_time' => time()
        ];
        return Redis::rpush(App::logListKey('strategy', $this->app['appcode']), json_encode($data, 320)) ? true : false;
    }

    /**
     * 策略返回判断
     * @param $strategyKey
     * @return array
     */
    protected function getStrategy($strategyKey)
    {
        return $this->strategyV5($strategyKey);
    }

    /**
     * 策略控制分厂商投放渠道
     * @param string $strategyKey
     * @return array
     */
    public function strategyV5($strategyKey = 'common')
    {
        $vendor = strtolower($this->params['vendor'] ?? '');
        $vendor = in_array($vendor, ['huawei', 'xiaomi', 'oppo', 'vivo']) ? $vendor : 'huawei';
        $launch_type = Account::isKeyAction($this->account) ? 1 : 0;
        $source = array_flip(AdConst::PUT_SOURCE_MAP)[$this->source];
        $list = DB::connection('uc')->table('ad_strategy')
            ->where('app_code', '=', $this->app['appcode'])
            ->where('source', 'like', "%{$source}%")
            ->where('vendor', 'like', "%{$vendor}%")
            ->where('key', 'like', "%{$strategyKey}%")
            ->whereRaw("JSON_CONTAINS(channel,'{$this->channel}')")
            ->whereRaw("JSON_CONTAINS(launch_type,'{$launch_type}')")
            ->where('deleted_time', '=', 0)
            ->where('status', '=', 1)
            ->select(['info', 'id'])
            ->orderBy('id')
            ->get()
            ->toArray();
        // 策略分组
        $index = ($this->device['create_time'] ?? 0) % (count($list) > 0 ? count($list) : 1);

        $config = $list[$index] ?? [];

        if (!empty($config)) {
            $info = json_decode($config['info'], true);
            $info['source'] = $this->source;
            $info['strategy_id'] = $config['id'];
            $info['launch_type'] = $launch_type;
        } else {
            $info = [
                'config' => [
                    'keepalive' => $strategyKey == 'common' ? true : false
                ],
                'ad_key' => []
            ];
            if ($strategyKey == 'common' && App::isProduct()) {
                $error_msg = '应用【' . $this->app['appcode'] . '】未能获取到广告策略，请检查' . PHP_EOL;
                $error_msg .= '策略参数：' . json_encode(['source' => $source, 'vendor' => $vendor, 'key' => $strategyKey, 'channel' => $this->channel, 'launch_type' => $launch_type]);
                Dding::push($error_msg);
            }
        }

        return $info;
    }

    /**
     * API配置信息
     */
    public function setApiConfig()
    {
        $this->apiConfig = config('api', []);
    }

    protected function clientAudit()
    {
        return strstr(($this->params['from'] ?? ''), 'client_audit') ? true : false;
    }


    protected function oldUser()
    {
        $table = App::tableDevice($this->app['appcode']) . '_temp';
        if (!Schema::hasTable($table)) {
            return [];
        }
        return DB::table($table)
            ->where('android_id', $this->params['androidid'])
            ->first();
    }

    protected function installTime()
    {
        $installTimestamp = $this->params['first_install_time'] ?? 0;
        if ($installTimestamp > 0 && $installTimestamp < strtotime('-3 days') * 1000) {
            DB::table(App::tableDevice($this->app['appcode']))
                ->where('android_id', $this->params['android_id'])
                ->where('ad_time', 0)
                ->update([
                    'ad_time' => time()
                ]);
            return true;
        }

        return false;
    }

    protected function lowerVersionUser()
    {
        // 安装时间超过3天启动
        if (!empty($this->params['app_list_time']) && !empty($this->app['package_name']) && is_array($this->params['app_list_time'])) {
            return ($this->params['app_list_time'][$this->app['package_name']] ?? 0) <= strtotime('-3 days') * 1000;
        }

        return false;
    }

    protected function risk()
    {
        try {
            $result = (new Risk())->login(
                $this->app['volc_appid'],
                $this->params['bd_did'] ?? '',
                $this->params['android_id'],
                time(),
                $this->params['model'] ?? '',
                $this->params['vendor'] ?? '',
                $this->params['web_ua'] ?? '',
                $this->params['os_version'] ?? '',
                $this->clientIp,
                $this->params['channel'] ?? 1,
                );
        } catch (\Exception $e) {

        }

        return ($result['score'] ?? 0) >= 600;
    }
}
