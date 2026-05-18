<?php
/**
 * APP请求基类
 */

namespace App\Http\Controllers\V1;

use App\Jobs\ControllerJob;
use App\Jobs\GuiyinJob;
use App\Services\Common\Active;
use App\Services\Common\App;
use App\Services\Common\Device;
use App\Services\Helper\Tools\LogPush;
use Cache;
use DB;
use Exception;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller;

class AppBaseController extends Controller
{
    /**
     * 业务参数
     * @var array
     */
    public $params;

    /**
     * 请求版本
     * @var string
     */
    public $version;

    /**
     * APP信息
     * @var array
     */
    public $app;

    /**
     * 渠道
     * @var int
     * @var
     */
    public $channel;

    /**
     * IP地址
     * @var
     */
    public $clientIp;

    /**
     * 忽略无效IMEI
     * @var array
     */
    protected $ignoreIMEI = [
        '000000000000000'
    ];

    /**
     * 忽略无效OAID
     * @var array
     */
    protected $ignoreOAID = [
        '00000000-0000-0000-0000-000000000000'
    ];

    /**
     * AndroidID不存在时的其他唯一标识字段
     * @var array
     */
    protected $identifiedKey = [
        'oaid',
        'imei',
        'dmac',
        'simsn',
        'dimsi'
    ];

    /**
     * 广告上报接口
     * @var string
     */
    protected $pathAd = 'v1/event/ad';

    protected $path;

    public function __construct(Request $request)
    {
        $this->path = $request->path();

        $params = $request->get('_param');

        $params['data']['androidid'] = $params['data']['android_id'] ?? ($params['data']['androidid'] ?? '');
        // AndroidId唯一标识不存在的特殊处理
        if (empty($params['data']['androidid'])) {
            foreach ($this->identifiedKey as $field) {
                if (!empty($params['data'][$field])) {
                    $params['data']['androidid'] = $field . ':' . $params['data'][$field];
                    break;
                }
            }
        }
        $params['data']['umid'] = $params['data']['uumid'] ?? '';
        // AndroidID字段处理
        $params['data']['android_id'] = $params['data']['androidid'] ?? '';
        // OAID
        $params['data']['oaid'] = $params['data']['oaid'] ?? '';
        $params['data']['oaid'] = in_array($params['data']['oaid'], $this->ignoreOAID, true) ? '' : $params['data']['oaid'];
        // IMEI
        $params['data']['imei'] = $params['data']['imei'] ?? '';
        $params['data']['imei'] = in_array($params['data']['imei'], $this->ignoreIMEI, true) ? '' : $params['data']['imei'];
        // 兼容客户端错误 空格问题
        $params['data']['harmony'] = $params['data']['harmony'] ?? ($params['data']['harmony '] ?? 0);
        // IP
        $this->clientIp = $request->header('x-forwarded-for') ?? $request->getClientIp();
        $params['data']['ip'] = $this->clientIp;
        // 系统版本
        $params['data']['os_version'] = $params['data']['os_version'] ?? App::osVersion($params['data']['os_sdk_version'] ?? '');
        $this->setParams($params['data'] ?? []);
        $keys = [
            'location',
            'wifilist',
            'app_list',
            'app_list_time',
        ];
        foreach ($keys as $key) {
            if (!empty($this->params[$key])) {
                $this->params[$key] = is_array($this->params[$key]) ? $this->params[$key] : json_decode($this->params[$key], true);
            }
        }
        $this->setApp($params['_app'] ?? [], strval($params['version'] ?? ''), intval($params['data']['channel'] ?? 0));
        $this->logDevice();
    }

    /**
     * 业务参数
     * @param $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }

    /**
     * APP请求信息
     * @param array $app
     * @param string $version
     * @param int $channel
     */
    public function setApp(array $app, string $version, int $channel)
    {
        $this->app = $app;
        $this->version = $version;
        $this->channel = $channel;
    }

    /**
     * 队列任务
     */
    public function job()
    {
        dispatch((new ControllerJob($this)));
    }

    /**
     * 异步待处理方法
     */
    public function handle()
    {

    }

    /**
     * 是否黑名单
     * @param bool $isAd 是否新归因设备
     * @return bool
     */
    protected function isBlacklist($isAd = false)
    {
        if (!App::isProduct()) {
            return false;
        }
//        if (!in_array($this->app['appcode'] ?? '', ['ttgjx', 'qybh']) && ($this->params['app_debug'] ?? false)) {
//            // app列表包含debug版本
//            $this->insertBlack([
//                'app_debug' => true
//            ]);
//            return true;
//        }
        if (($this->params['is_adb'] ?? false)) {
            // 打开开发者模式
            $this->insertBlack([
                'is_adb' => true
            ]);
            return true;
        }
        // 新归因设备
        if ($isAd) {
            return false;
        }

        // 应用列表
        if (!empty($this->params['app_list_time']) && is_array($this->params['app_list_time'])) {
            // 短期内安装多个应用
            $mt = strtotime(date('Y-m-d', strtotime('-3 days'))) * 1000;
            $appNum = 0;
            foreach ($this->params['app_list_time'] as $t) {
                $appNum += ($t >= $mt ? 1 : 0);
            }

            if ($appNum >= 30) {
                $this->insertBlack([
                    'app_list_time' => true
                ]);
                return true;
            }
        }

        // app列表
        if (!empty($this->params['app_list']) && is_array($this->params['app_list'])) {
            $appBlacklist = config('api.app_blacklist', []);
            $app = array_intersect($appBlacklist, $this->params['app_list']);
            if (!empty($app)) {
                $this->insertBlack([
                    'app_blacklist' => $app
                ]);
                return true;
            }
        }

        foreach (Device::$fields as $field) {
            if (!empty($this->params[$field]) && Device::isBlack($field, $this->params[$field])) {
                $this->insertBlack([
                    $field => $this->params[$field]
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * 入库黑名单
     * @param array $target
     */
    protected function insertBlack($target = [])
    {
        $ssid = $bssid = [];
        if (!empty($this->params['wifilist']) && is_array($this->params['wifilist'])) {
            foreach ($this->params['wifilist'] as $v) {
                if (!empty($v['ssid'])) {
                    $ssid[] = $v['ssid'];
                }
                if (!empty($v['bssid'])) {
                    $bssid[] = $v['bssid'];
                }
                // 兼容错误版本入参ab
                if (!empty($v['a'])) {
                    $ssid[] = $v['a'];
                }
                if (!empty($v['b'])) {
                    $bssid[] = $v['b'];
                }
            }
        }

        Device::addBlackDevice([
            'imei' => $this->params['imei'] ?? '',
            'oaid' => $this->params['oaid'] ?? '',
            'android_id' => $this->params['android_id'] ?? '',
            'vendor' => $this->params['vendor'] ?? '',
            'model' => $this->params['model'] ?? '',
            'ip' => $this->params['ip'] ?? '',
            'ssid' => json_encode($ssid, 320),
            'bssid' => json_encode($bssid, 320),
            'station' => json_encode((empty($this->params['net']) || !is_array($this->params['net'])) ? [] : $this->params['net'], 320),
            'target' => json_encode($target, 320),
            'appcode' => $this->app['appcode'] ?? '',
            'version' => $this->version ?? '',
        ]);
    }

    /**
     * 入库黑名单
     * @param array $target
     */
    protected function insertLandingBlack($target = [])
    {
        $ssid = $bssid = [];
        if (!empty($this->params['wifilist']) && is_array($this->params['wifilist'])) {
            foreach ($this->params['wifilist'] as $v) {
                if (!empty($v['ssid'])) {
                    $ssid[] = $v['ssid'];
                }
                if (!empty($v['bssid'])) {
                    $bssid[] = $v['bssid'];
                }
                // 兼容错误版本入参ab
                if (!empty($v['a'])) {
                    $ssid[] = $v['a'];
                }
                if (!empty($v['b'])) {
                    $bssid[] = $v['b'];
                }
            }
        }
        try {
            DB::table('com_blacklist_landing')
                ->insertOrIgnore([
                    'imei' => $this->params['imei'] ?? '',
                    'oaid' => $this->params['oaid'] ?? '',
                    'android_id' => $this->params['android_id'] ?? '',
                    'vendor' => $this->params['vendor'] ?? '',
                    'model' => $this->params['model'] ?? '',
                    'ip' => $this->params['ip'] ?? '',
                    'ssid' => json_encode($ssid, 320),
                    'bssid' => json_encode($bssid, 320),
                    'station' => json_encode((empty($this->params['net']) || !is_array($this->params['net'])) ? [] : $this->params['net'], 320),
                    'target' => json_encode($target, 320),
                    'appcode' => $this->app['appcode'] ?? '',
                    'version' => $this->version ?? '',
                    'create_time' => time()
                ]);
        } catch (Exception $e) {
            LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
        }
        // 删除缓存
        Cache::forget('blacklist-landing');
    }

    /**
     * 设备信息
     * @param string $appcode
     * @return array
     */
    protected function getDevice(string $appcode = null)
    {
        $table = App::tableDevice($appcode ?? $this->app['appcode']);
        $device = DB::table($table)
            ->useWritePdo()
            ->where('android_id', $this->params['android_id'])
            ->first();
        return (array)$device;
    }

    /**
     * 设备记录
     */
    protected function logDevice()
    {
        if (empty($this->params['android_id'])) {
            return;
        }

        $tableDevice = App::tableDevice($this->app['appcode']);
        $device = DB::table($tableDevice)
            ->useWritePdo()
            ->where('android_id', $this->params['android_id'] ?? '')
            ->first();
        if (empty($device)) {
            DB::table($tableDevice)
                ->insertOrIgnore([
                    'version' => $this->version,
                    'channel' => $this->channel,
                    'umid' => $this->params['umid'] ?? '',
                    'imei' => $this->params['imei'] ?? '',
                    'oaid' => $this->params['oaid'] ?? '',
                    'android_id' => $this->params['android_id'] ?? '',
                    'vendor' => $this->params['vendor'] ?? '',
                    'model' => $this->params['model'] ?? '',
                    'mac' => $this->params['req_id'] ?? ($this->params['dmac'] ?? ''),
                    'os_version' => App::osVersion($this->params['os_sdk_version'] ?? ''),
                    'location' => json_encode(Device::location($this->clientIp), 320),
                    'wifi_list' => json_encode(!empty($this->params['wifilist']) ? $this->params['wifilist'] : [], 320),
                    'app_list' => json_encode(!empty($this->params['app_list']) ? $this->params['app_list'] : [], 320),
                    'net' => json_encode(!empty($this->params['net']) ? $this->params['net'] : [], 320),
                    'ip' => $this->clientIp,
                    'more_keep' => $this->params['harmony'] ?? 0,// 1:鸿蒙系统 2:鸿蒙纯净模式
                    'account_id' => $this->channel == 14 ? "{$this->app['appcode']}-channel-{$this->channel}" : '', // 荣耀特殊渠道处理
                    'create_time' => time()
                ]);

//            // 定位｜异步存在时效问题暂时同步
//            dispatch(new DeviceLocationJob([
//                'table' => $tableDevice,
//                'ip' => $this->clientIp,
//                'android_id' => $this->params['android_id']
//            ]));

            // 归因
            if (($this->app['ad_status'] ?? 0) != App::$adStatus['agency']) {
                dispatch((new GuiyinJob([
                    'app_code' => $this->app['appcode'],
                    'android_id' => $this->params['android_id']
                ]))->delay(10));
            }

            $install_time = time();
            $active_time = 0;
        } else {
            $install_time = $device['create_time'];
            $active_time = $device['active_time'];

            $update = [];
            if (empty($device['umid']) && !empty($this->params['umid'])) {
                $update['umid'] = $this->params['umid'] ?? '';
            }
            if (empty($device['imei']) && !empty($this->params['imei'])) {
                $update['imei'] = $this->params['imei'] ?? '';
            }
            if (empty($device['oaid']) && !empty($this->params['oaid'])) {
                $update['oaid'] = $this->params['oaid'] ?? '';
            }
            if (empty($device['os_version']) && !empty($this->params['os_sdk_version'])) {
                $update['os_version'] = App::osVersion($this->params['os_sdk_version'] ?? '');
            }
            // 小说监测跟踪
            if (empty($device['mac']) && !empty($this->params['req_id'])) {
                $update['mac'] = $this->params['req_id'] ?? '';
            }
            if ((empty($device['app_list']) || empty(json_decode($device['app_list'], true))) && !empty($this->params['app_list'])) {
                $update['app_list'] = json_encode($this->params['app_list'] ?? []);
            }
            if ($device['more_keep'] == 0 && !empty($this->params['harmony'])) {
                $update['more_keep'] = $this->params['harmony'];
            }

            // 更新umid
            if (!empty($update)) {
                $update['update_time'] = time();
                DB::table($tableDevice)
                    ->where('id', $device['id'])
                    ->update($update);
            }
        }

        Active::device($this->app['appcode'], date('Ymd'), $this->params['android_id'], $install_time, $active_time, $this->path == $this->pathAd ? time() : 0);

        Active::activateKeep($this->app['appcode'], $this->params['android_id'], $active_time, $this->path == $this->pathAd ? time() : 0);

        Active::activateFill($this->app['appcode'], $this->params['android_id'], $device['account_id'] ?? '0', $active_time, $device['ad_time'] ?? 0, $this->path == $this->pathAd ? time() : 0);
    }

    /**
     * 落地页投放
     * @return bool
     */
    public function isLanding()
    {
        return $this->channel == 10;
    }

    /**
     * 落地页黑名单
     * @return bool
     */
    public function landingBlacklist()
    {
        if (!App::isProduct()) {
            return false;
        }
        $key = 'blacklist-landing';
        $data = Cache::get($key, []);
        if (empty($data)) {
            $list = DB::table('com_blacklist_landing')
                ->select([
                    'id',
                    'imei',
                    'oaid',
                    'android_id',
                    'ip',
                ])
                ->get()
                ->toArray();
            $jsonStr = [
                '[]',
                'null'
            ];
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
                if (!empty($v['ip'])) {
                    $data['ip'][$v['ip']] = $v['id'];
                }
                if (!empty($v['ssid']) && !in_array($v['ssid'], $jsonStr)) {
                    $v['ssid'] = json_decode($v['ssid'], true);
                    if (!empty($v['ssid'])) {
                        foreach ($v['ssid'] as $v2) {
                            if (!empty($v2)) {
                                $data['ssid'][$v2] = $v['id'];
                            }
                        }
                    }
                }
                if (!empty($v['bssid']) && !in_array($v['bssid'], $jsonStr)) {
                    $v['bssid'] = json_decode($v['bssid'], true);
                    if (!empty($v['bssid'])) {
                        foreach ($v['bssid'] as $v2) {
                            if (!empty($v2)) {
                                $data['bssid'][$v2] = $v['id'];
                            }
                        }
                    }
                }
                if (!empty($v['station']) && !in_array($v['station'], $jsonStr)) {
                    $v['station'] = json_decode($v['station'], true);
                    if (!empty($v['station'])) {
                        foreach ($v['station'] as $v3) {
                            if (!empty($v3)) {
                                // 存储json顺序
                                // GSM:mCid mLac mPsc
                                // CDMA:mSystemId mNetworkId mBaseStationId mBaseStationLatitude mBaseStationLongitude
                                $str = implode('', $v3);
                                $data['station'][$str] = $str;
                            }
                        }
                    }
                }
            }
            Cache::put($key, $data, 3600);
        }
        // 设备在黑名单列表
        $arr = [
            'imei' => 'imei',
            'android_id' => 'android_id',
            'oaid' => 'oaid',
            'mac' => 'dmac',
            'ip' => 'ip',
        ];
        foreach ($arr as $k => $v) {
            if (!empty($this->params[$v]) && isset($data[$k][$this->params[$v]])) {
//                $this->insertBlack([
//                    $k => $this->params[$v]
//                ]);
                return true;
            }
        }
        // ssid、bssid、stationID中任意一个参数在设备黑名单
        if (!empty($this->params['wifilist']) && is_array($this->params['wifilist'])) {
            foreach ($this->params['wifilist'] as $v) {
                if (!empty($v['ssid']) && isset($data['ssid'][$v['ssid']])) {
//                    $this->insertBlack([
//                        'ssid' => $v['ssid']
//                    ]);
                    return true;
                }
                if (!empty($v['bssid']) && isset($data['bssid'][$v['bssid']])) {
//                    $this->insertBlack([
//                        'bssid' => $v['bssid']
//                    ]);
                    return true;
                }
                // 兼容错误版本入参ab
                if (!empty($v['a']) && isset($data['ssid'][$v['a']])) {
//                    $this->insertBlack([
//                        'ssid' => $v['a']
//                    ]);
                    return true;
                }
                if (!empty($v['b']) && isset($data['bssid'][$v['b']])) {
//                    $this->insertBlack([
//                        'bssid' => $v['b']
//                    ]);
                    return true;
                }
            }
        }
        // stationID在设备黑名单
        if (!empty($this->params['net']) && is_array($this->params['net'])) {
            // GSM:mCid mLac mPsc
            // CDMA:mSystemId mNetworkId mBaseStationId mBaseStationLatitude mBaseStationLongitude
            if (!empty($this->params['net']['GSM'])) {
                $gsm = [
                    'mCid' => $this->params['net']['GSM']['mCid'] ?? '',
                    'mLac' => $this->params['net']['GSM']['mLac'] ?? '',
                    'mPsc' => $this->params['net']['GSM']['mPsc'] ?? '',
                ];
                if (isset($data['station'][implode('', $gsm)])) {
//                    $this->insertBlack([
//                        'net_GSM' => $gsm
//                    ]);
                    return true;
                }
            }
            if (!empty($this->params['net']['CDMA'])) {
                $cdma = [
                    'mSystemId' => $this->params['net']['GSM']['mSystemId'] ?? '',
                    'mNetworkId' => $this->params['net']['GSM']['mNetworkId'] ?? '',
                    'mBaseStationId' => $this->params['net']['GSM']['mBaseStationId'] ?? '',
                    'mBaseStationLatitude' => $this->params['net']['GSM']['mBaseStationLatitude'] ?? '',
                    'mBaseStationLongitude' => $this->params['net']['GSM']['mBaseStationLongitude'] ?? '',
                ];
                if (isset($data['station'][implode('', $cdma)])) {
//                    $this->insertBlack([
//                        'net_CDMA' => $cdma
//                    ]);
                    return true;
                }
            }
        }
        // ssid中包含bytedance、tencent、catr、cttl、test、nj9fa任意一个，则进入黑名单
        if (!empty($this->params['wifilist']) && is_array($this->params['wifilist'])) {
            $wifilist = json_encode($this->params['wifilist'], 320);
            $ssid = [
                'bytedance',
                'tencent',
                'catr',
                'cttl',
//                'test',
                'nj9fa'
            ];
            foreach ($ssid as $v) {
                if (!empty(stristr($wifilist, $v))) {
//                    $this->insertBlack([
//                        'wifi' => $v
//                    ]);
                    return true;
                }
            }
        }
        // app列表中包含
        if (!empty($this->params['app_list']) && is_array($this->params['app_list'])) {
            $appBlacklist = config('api.app_blacklist', []);
            $app = array_intersect($appBlacklist, $this->params['app_list']);
            if (!empty($app)) {
                $this->insertBlack([
                    'app' => $app
                ]);
                return true;
            }
        }
        // 机型中包含rk3399_mid
        if (!empty($this->params['model']) && !empty(stristr($this->params['model'], 'rk3399_mid'))) {
//            $this->insertBlack([
//                'model' => 'rk3399_mid'
//            ]);
            return true;
        }
        // Google机型
        if (($this->params['vendor'] ?? '') == 'Google') {
//            $this->insertBlack([
//                'vendor' => 'Google'
//            ]);
            return true;
        }
        return false;
    }

    /**
     * 应用列表
     */
    public function appList()
    {
        if (!empty($this->params['app_list_time'])) {
            $appList = is_array($this->params['app_list_time']) ? $this->params['app_list_time'] : json_decode($this->params['app_list_time'], true);
            DB::table('com_app_list')
                ->insert([
                    'app_code' => $this->app['appcode'] ?? '',
                    'device_id' => $this->params['android_id'] ?? '',
                    'app_list' => json_encode($appList),
                    'create_time' => time()
                ]);
        }
    }


    public function location()
    {
        if (!App::isProduct()) {
            return false;
        }
        // 通用
        $citys = ['北京', '上海', '广州', '深圳', '南京'];
        // 华为
        $citysHw = ['南京', '东莞', '重庆', '贵阳'];
        // OPPO
        $citysOp = ['成都', '东莞', '武汉'];
        // vivo
        $citysVi = ['南京', '重庆'];

        $table = App::tableDevice($this->app['appcode']);
        $location = json_decode(DB::table($table)
            ->where('android_id', $this->params['android_id'])
            ->where('create_time', '>=', strtotime('2023-04-20 11:50:00'))
            ->value('location'), true);

        if (in_array($this->clientIp, config('api.whitelistIp', [])) || ($location['white'] ?? 0)) {
            return false;
        }

        if (!empty($location)) {
            foreach ($citys as $city) {
                if (strstr($location['city'], $city)) {
                    return true;
                }
            }

            if ($this->channel == 1) {
                foreach ($citysHw as $city) {
                    if (strstr($location['city'], $city)) {
                        return true;
                    }
                }
            }

            if ($this->channel == 3) {
                foreach ($citysOp as $city) {
                    if (strstr($location['city'], $city)) {
                        return true;
                    }
                }
            }

            if ($this->channel == 4) {
                foreach ($citysVi as $city) {
                    if (strstr($location['city'], $city)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
