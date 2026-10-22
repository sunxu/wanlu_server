<?php
/**
 * APP通用
 */

namespace App\Services\Common;

use Cache;
use DB;
use Illuminate\Support\Facades\Redis;
use Schema;

class App
{
    /**
     * 投放状态
     * @var array
     */
    public static array $adStatus = [
        'stop' => 0,// 停投
        'self' => 1,// 自投
        'agency' => 2,// 联运代投
    ];

    /**
     * 应用类型
     * @var array
     */
    public static array $appType = [
        'tool' => 1000,// 工具
        'game' => 2000,// 网赚
    ];

    public static array $appSubType = [
        'tool_wifi' => 1001,// WiFi
        'tool_clear' => 1002,// 清理
        'tool_weather' => 1003,// 天气
        'tool_box' => 1004,// 工具盒
        'tool_pay' => 1005,// 付费工具
        'tool_browser' => 1006,// 浏览器
        'tool_calendar' => 1007,// 万年历
        'tool_quick' => 1010,// 快应用
        'game_video' => 2001,// 短视频
        'game_song' => 2002,// 猜歌
    ];

    /**
     * 应用APPID缓存键名
     * @param string $appId
     * @return string
     */
    public static function getAppCacheIdKey(string $appId)
    {
        return 'appid:' . $appId;
    }

    /**
     * 应用标识缓存键名
     * @param string $appCode
     * @return string
     */
    public static function getAppCacheCodeKey(string $appCode)
    {
        return 'appcode:' . $appCode;
    }

    /**
     * APP信息
     * @param string $appCode
     * @return array
     */
    public static function getAppByCode(string $appCode): array
    {
        $key = self::getAppCacheCodeKey($appCode);

        $app = Cache::get($key);
        if (!is_null($app)) {
            return $app;
        }

        $app = self::appInfoList(null, [$appCode])[0] ?? [];
        Cache::put($key, $app, 24 * 3600);

        return $app;
    }

    /**
     * APP信息
     * @param string $appId
     * @return array
     */
    public static function getAppById(string $appId): array
    {
        $key = self::getAppCacheIdKey($appId);

        $app = Cache::get($key);
        if (!is_null($app)) {
            return $app;
        }

        $app = self::appInfoList([$appId])[0] ?? [];
        Cache::put($key, $app, 24 * 3600);

        return $app;
    }

    public static function extra(string $appCode): array
    {
        $app = self::getAppByCode($appCode);
        return $app['extra'] ?? [];
    }

    /**
     * 应用列表
     * @param array $appIdArr
     * @param array $appCodeArr
     * @return array
     */
    public static function appInfoList(array $appIdArr = null, array $appCodeArr = null)
    {
        // 应用信息
        $sql = DB::connection('uc')->table('app as a')
            ->leftJoin('app_company as c', 'a.media_company_id', '=', 'c.company_id')
            ->select([
                'a.app_id',
                'a.sign_secret_key as appid',
                'a.app_name as appname',
                'a.app_code as appcode',
                'a.params_secret_key as appkey',
                'a.status',
                'a.ad_status',
                'a.package_name',
                'a.type',
                'a.sub_type',
                'a.volc_appid',
                'a.media_conf',
                'a.extra',
                'c.media_conf_csj',
                'c.media_conf_ylh',
                'c.media_conf_ks',
            ]);

        !empty($appIdArr) && $sql->whereIn('sign_secret_key', $appIdArr);
        !empty($appCodeArr) && $sql->whereIn('app_code', $appCodeArr);

        $appList = $sql->orderBy('a.app_id', 'desc')->get()->toArray();

        foreach ($appList as &$app) {
            foreach (['media_conf', 'media_conf_csj', 'media_conf_ylh', 'media_conf_ks', 'extra'] as $k) {
                $app[$k] = $app[$k] ? json_decode($app[$k], true) : [];
            }
        }

        return $appList;
    }

    /**
     * 版本补全号
     * @param string $version 例如：2.3.6
     * @return string
     */
    public static function versionNum(string $version): string
    {
        // 格式验证
        if (substr_count($version, '.') != 2) {
            $version = '1.0.0';
        }
        $arr = explode('.', $version);
        $versionNum = '';
        foreach ($arr as $v) {
            $versionNum .= sprintf("%03d", $v);
        }
        return $versionNum;
    }

    /**
     * 策略版本
     * @param string $appCode
     * @param string $version
     * @return array
     */
    public static function getStrategyVersion(string $appCode, string $version): array
    {
        // 版本号
        $versionNum = self::versionNum($version);
        // 策略版本
        $list = DB::table('app_version')
            ->select([
                'id as version_id',
                'version',
                'audit_counter',
                'audit_status',
                'test_status',
                'extension',
            ])
            ->where('app_code', $appCode)
            ->get()
            ->toArray();
        $versionList = [];
        foreach ($list as $v) {
            // 格式转换
            $v['audit_status'] = empty($v['audit_status']) ? [] : json_decode($v['audit_status'], true);
            $v['extension'] = empty($v['extension']) ? [] : json_decode($v['extension'], true);
            $versionList[self::versionNum($v['version'])] = $v;
        }
        // 存在当前版本
        if (isset($versionList[$versionNum])) {
            $appVersion = $versionList[$versionNum];
        } else {
            // 版本排序
            ksort($versionList);
            // 版本匹配|优先使用低版本
            foreach ($versionList as $k => $v) {
                if ($versionNum < $k) {
                    $appVersion = $v;
                    break;
                }
            }
            // 无匹配使用最新版本
            if (empty($appVersion) && !empty($versionList)) {
                $appVersion = array_pop($versionList);
            }
        }

        return $appVersion ?? [];
    }

    /**
     * 是否存在APP
     * @param string $appCode
     * @return bool
     */
    public static function hasApp(string $appCode)
    {
        return Schema::hasTable(App::tableDevice($appCode));
    }

    /**
     * 打点队列键
     * @param string $key
     * @param string $appcode
     * @return string
     */
    public static function eventListKey(string $key, string $appcode): string
    {
        return sprintf('%s:event:%s:%s', env('APP_NAME'), $appcode, $key);
    }

    /**
     * 异步队列键
     * @param string $key
     * @param string $appcode
     * @return string
     */
    public static function logListKey(string $key, string $appcode): string
    {
        return sprintf('%s:log:%s:%s', env('APP_NAME'), $key, $appcode);
    }

    /**
     * 批量获取队列数据
     * @param string $key
     * @param int $num
     * @return array
     */
    public static function batchGetList(string $key, int $num = 0): array
    {
        if ($num <= 0) {
            $num = Redis::llen($key);
        }
        $redis = Redis::multi();
        $redis->lrange($key, 0, $num - 1);
        $redis->ltrim($key, $num, -1);
        $data = $redis->exec();
        return $data[0] ?? [];
    }

    /**
     * 批量插入队列
     * @param string $key
     * @param array $data
     */
    public static function batchAddList(string $key, array $data)
    {
        $redis = Redis::connection();
        $page_size = 10000;//单次最多插入数
        $total_count = count($data);//总数量
        $total_page = ceil($total_count / $page_size);//总页数


        for ($page = 0; $page < $total_page; $page++) {
            $limit_start = $page * $page_size;
            $i = 0;
            $params = [];
            while ($i < $page_size && isset($data[$limit_start + $i])) {
                $list_data = $data[$limit_start + $i];
                $list_data = is_array($list_data) ? json_encode($list_data) : $list_data;
                $params[] = $list_data;
                $i++;
            }
            array_unshift($params, $key);
            call_user_func_array([$redis, 'rpush'], $params);
        }

    }


    /**
     * 打点有序集合键
     * @param string $key
     * @param string $appcode
     * @return string
     */
    public static function eventSourceSetKey(string $key, string $appcode): string
    {
        return sprintf('%s:event:source:set:%s:%s', env('APP_NAME'), $appcode, $key);
    }

    /**
     * 安卓系统版本转换
     * @param string $osSdkVersion
     * @return string
     */
    public static function osVersion(string $osSdkVersion): string
    {
        $androidVersion = config('api.android_version', []);
        $num = substr($osSdkVersion, strripos($osSdkVersion, "_") + 1);
        return $androidVersion[$num] ?? '';
    }

    /**
     * 设备表
     * @param string $appcode
     * @return string
     */
    public static function tableDevice(string $appcode): string
    {
        return sprintf('app_%s', $appcode);
    }

    /**
     * 策略日志
     * @param string $appcode
     * @return string
     */
    public static function tableLogStrategy(string $appcode): string
    {
        return sprintf('app_%s_log', $appcode);
    }

    /**
     * 设备日志
     * @param string $appcode
     * @return string
     */
    public static function tableLogDevice(string $appcode): string
    {
        return sprintf('app_%s_device_log', $appcode);
    }

    /**
     * 根据渠道返回归因数据表
     * @param string $appcode
     * @param int $source
     * @return string
     */
    public static function tableAdBySource(string $appcode, int $source)
    {
        switch ($source) {
            case 2:
                $tableAd = self::tableAdKuaishou($appcode);
                break;
            case 3:
                $tableAd = self::tableAdOppo($appcode);
                break;
            case 4:
                $tableAd = self::tableAdHuawei($appcode);
                break;
            case 5:
                $tableAd = self::tableAdVivo($appcode);
                break;
            case 6:
                $tableAd = self::tableAdBd($appcode);
                break;
            case 8:
                $tableAd = self::tableAdTencent($appcode);
                break;
            case 7:
                $tableAd = self::tableAdHonor($appcode);
                break;
            case 1:
            default:
                $tableAd = self::tableAdJuliang($appcode);
                break;
        }
        return $tableAd;
    }

    /**
     * 腾讯营销推广表
     * @param string $appcode
     * @return string
     */
    public static function tableAdTencent(string $appcode): string
    {
        return sprintf('app_%s_tx', $appcode);
    }

    public static function tableAdJuliang(string $appcode): string
    {
        return sprintf('app_%s_ad', $appcode);
    }

    /**
     * 快手推广表
     * @param string $appcode
     * @return string
     */
    public static function tableAdKuaishou(string $appcode): string
    {
        return sprintf('app_%s_ks', $appcode);
    }

    /**
     * OPPO推广表
     * @param string $appcode
     * @return string
     */
    public static function tableAdOppo(string $appcode): string
    {
        return sprintf('app_%s_oppo', $appcode);
    }

    /**
     * 华为推广表
     * @param string $appcode
     * @return string
     */
    public static function tableAdHuawei(string $appcode): string
    {
        return sprintf('app_%s_huawei', $appcode);
    }

    /**
     * vivo推广表
     * @param string $appcode
     * @return string
     */
    public static function tableAdVivo(string $appcode): string
    {
        return sprintf('app_%s_vivo', $appcode);
    }

    /**
     * 百度推广表
     * @param string $appcode
     * @return string
     */
    public static function tableAdBd(string $appcode): string
    {
        return sprintf('app_%s_bd', $appcode);
    }

    /**
     * 荣耀推广表
     * @param string $appcode
     * @return string
     */
    public static function tableAdHonor(string $appcode): string
    {
        return sprintf('app_%s_honor', $appcode);
    }

    /**
     * 打点广告表｜请求
     * @param string $appcode
     * @return string
     */
    public static function tableEventAdRequest(string $appcode): string
    {
        return sprintf('app_%s_event_ad_request', $appcode);
    }

    /**
     * 打点广告表|填充
     * @param string $appcode
     * @return string
     */
    public static function tableEventAdFull(string $appcode): string
    {
        return sprintf('app_%s_event_ad_full', $appcode);
    }

    /**
     * 打点广告表|展现
     * @param string $appcode
     * @return string
     */
    public static function tableEventAdShow(string $appcode): string
    {
        return sprintf('app_%s_event_ad_show', $appcode);
    }


    /**
     * 打点广告表|点击
     * @param string $appcode
     * @return string
     */
    public static function tableEventAdClick(string $appcode): string
    {
        return sprintf('app_%s_event_ad_click', $appcode);
    }

    /**
     * 打点广告表|回收
     * @param string $appcode
     * @return string
     */
    public static function tableEventAdRevenue(string $appcode): string
    {
        return sprintf('app_%s_event_ad_revenue', $appcode);
    }

    /**
     * 打点广告类型表
     * @param string $appcode
     * @param string $event
     * @return string
     */
    public static function tableEventAdType(string $appcode, string $event): string
    {
        return sprintf('app_%s_event_%s', $appcode, $event);
    }


    /**
     * 广告打点分时间统计表
     * @param string $appcode
     * @return string
     */
    public static function tableIntervalGroup(string $appcode): string
    {
        return sprintf('app_%s_event_interval_group', $appcode);
    }

    /**
     * 设备活跃留存表
     * @param string $appcode
     * @return string
     */
    public static function tableActiveKeep(string $appcode): string
    {
        return sprintf('app_%s_active_keep', $appcode);
    }

    /**
     * 打点外弹表
     * @param string $appcode
     * @return string
     */
    public static function tableEventDialog(string $appcode): string
    {
        return sprintf('app_%s_event_dialog', $appcode);
    }

    /**
     * 打点信息流表
     * @param string $appcode
     * @return string
     */
    public static function tableEventInformation(string $appcode): string
    {
        return sprintf('app_%s_event_information', $appcode);
    }

    /**
     * 打点保活表
     * @param string $appcode
     * @return string
     */
    public static function tableEventActive(string $appcode): string
    {
        return sprintf('app_%s_active', $appcode);
    }

    /**
     * 支付交易
     * @param string $appcode
     * @return string
     */
    public static function tableTrade(string $appcode): string
    {
        $table = sprintf('app_%s_trade', $appcode);
        $copy = sprintf('app_%s_trade', 'default');
        if (!Schema::hasTable($table)) {
            self::createTable($table, $copy);
        }
        return $table;
    }

    /**
     * 复制表
     * @param string $tableName
     * @param string $tableNameCopy
     */
    public static function createTable(string $tableName, string $tableNameCopy)
    {
        DB::statement("
                CREATE TABLE IF NOT EXISTS {$tableName} LIKE {$tableNameCopy}
            ");
    }

    /**
     * 应用列表
     * @param array $appType 应用大类
     * @param array $code 应用标识
     * @param array $adStatus 投放状态
     * @param array $appSubType 应用子类
     * @return array
     */
    public static function codeList($appType = [], $code = [], $adStatus = [], $appSubType = [])
    {
        $sql = DB::connection('uc')->table('app')->where('status', 1);
        !empty($appType) && $sql->whereIn('type', $appType);
        !empty($code) && $sql->whereIn('app_code', $code);
        !empty($adStatus) && $sql->whereIn('ad_status', $adStatus);
        !empty($appSubType) && $sql->whereIn('sub_type', $appSubType);
        return $sql->orderBy('app_id', 'desc')
            ->pluck('app_name', 'app_code')
            ->toArray();
    }

    /**
     * 是否生产环境
     * @return bool
     */
    public static function isProduct()
    {
        return env('APP_ENV') == 'production' ? true : false;
    }

    /**
     * 获取留存json模板
     *
     * ago_day（几日留存）
     * keep_num（留存数）
     * ago_num（当日活跃数）
     * keep_rate（留存率 = 留存数 / 当日活跃数）
     *
     * @return array[]
     */
    public static function keepJson(): array
    {
        return [
            'keep1' => ['ago_day' => 1, 'keep_num' => null, 'ago_num' => null, 'keep_rate' => null],
            'keep2' => ['ago_day' => 2, 'keep_num' => null, 'ago_num' => null, 'keep_rate' => null],
            'keep3' => ['ago_day' => 3, 'keep_num' => null, 'ago_num' => null, 'keep_rate' => null],
            'keep4' => ['ago_day' => 4, 'keep_num' => null, 'ago_num' => null, 'keep_rate' => null],
            'keep5' => ['ago_day' => 5, 'keep_num' => null, 'ago_num' => null, 'keep_rate' => null],
            'keep6' => ['ago_day' => 6, 'keep_num' => null, 'ago_num' => null, 'keep_rate' => null],
            'keep7' => ['ago_day' => 7, 'keep_num' => null, 'ago_num' => null, 'keep_rate' => null],
            'keep8' => ['ago_day' => 8, 'keep_num' => null, 'ago_num' => null, 'keep_rate' => null],
            'keep9' => ['ago_day' => 9, 'keep_num' => null, 'ago_num' => null, 'keep_rate' => null],
            'keep10' => ['ago_day' => 10, 'keep_num' => null, 'ago_num' => null, 'keep_rate' => null],
        ];
    }

    /**
     * 获取回本率json模板
     *
     * ago_day（几日回本率）
     * revenue（回收数）
     * cost（消耗数）
     * recovery_rate（回本率 = 回收数 / 消耗数）
     *
     * @return array[]
     */
    public static function recoveryJson(): array
    {
        return [
            'recovery0' => ['ago_day' => 0, 'revenue' => null, 'cost' => null, 'recovery_rate' => null],
            'recovery1' => ['ago_day' => 1, 'revenue' => null, 'cost' => null, 'recovery_rate' => null],
            'recovery2' => ['ago_day' => 2, 'revenue' => null, 'cost' => null, 'recovery_rate' => null],
            'recovery3' => ['ago_day' => 3, 'revenue' => null, 'cost' => null, 'recovery_rate' => null],
            'recovery4' => ['ago_day' => 4, 'revenue' => null, 'cost' => null, 'recovery_rate' => null],
            'recovery5' => ['ago_day' => 5, 'revenue' => null, 'cost' => null, 'recovery_rate' => null],
            'recovery6' => ['ago_day' => 6, 'revenue' => null, 'cost' => null, 'recovery_rate' => null],
            'recovery7' => ['ago_day' => 7, 'revenue' => null, 'cost' => null, 'recovery_rate' => null],
            'recovery8' => ['ago_day' => 8, 'revenue' => null, 'cost' => null, 'recovery_rate' => null],
            'recovery9' => ['ago_day' => 9, 'revenue' => null, 'cost' => null, 'recovery_rate' => null],
            'recovery10' => ['ago_day' => 10, 'revenue' => null, 'cost' => null, 'recovery_rate' => null],
        ];
    }
}
