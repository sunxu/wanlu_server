<?php

namespace App\Services\Mkt;

use App\Constants\AdConst;
use App\Jobs\DeviceActiveCustomJob;
use App\Jobs\DeviceActiveJob;
use App\Jobs\GuiyinJob;
use App\Services\Common\Account;
use App\Services\Common\App;
use App\Services\Helper\Helper;
use DB;
use Exception;
use Schema;
use Log;

class GuiyinService
{
    /**
     * 最大归因周期天数
     * @var int
     */
    protected static $maxDays = 2;

    /**
     * @param string $appcode
     * @param array $device
     * @return array
     * @throws Exception
     */
    public static function handle(string $appcode, array $device)
    {
        $sourceJl = AdConst::PUT_SOURCE_MAP[AdConst::PUT_SOURCE_JL];
        $sourceKS = AdConst::PUT_SOURCE_MAP[AdConst::PUT_SOURCE_KS];
        $sourceOP = AdConst::PUT_SOURCE_MAP[AdConst::PUT_SOURCE_OP];
        $sourceHW = AdConst::PUT_SOURCE_MAP[AdConst::PUT_SOURCE_HW];
        $sourceVI = AdConst::PUT_SOURCE_MAP[AdConst::PUT_SOURCE_VI];
        $sourceBD = AdConst::PUT_SOURCE_MAP[AdConst::PUT_SOURCE_BD];
        $sourceHONOR = AdConst::PUT_SOURCE_MAP[AdConst::PUT_SOURCE_HONOR];

        // 优先级别是OAID（可获取、一致性）>IMEI（Android10+无法获取、一致性）>AndoridID（可获取、非一致性）>MAC（Android10+无法获取、一致性）
        $filter = [
            $sourceJl => [],// 巨量
            $sourceKS => [],// 快手
            $sourceOP => [],// OPPO
            $sourceHW => [],// 华为
            $sourceVI => [],// vivo
            $sourceBD => [],// 百度
            $sourceHONOR => [],
        ];

        if (!empty($deivce['req_id'])) {
            $filter[$sourceJl][] = function ($sql) use ($device) {
                $sql->where('request_id', '=', $device['req_id']);
            };
        }

        if (!empty($device['oaid'])) {
            $filter[$sourceJl][] = function ($sql) use ($device) {
                $sql->where('oaid', '=', $device['oaid']);
            };
            $filter[$sourceKS][] = function ($sql) use ($device) {
                $sql->where('oaid', '=', $device['oaid']);
            };
            $filter[$sourceKS][] = function ($sql) use ($device) {
                $sql->where('oaid', '=', md5($device['oaid']));
            };
            $filter[$sourceOP][] = function ($sql) use ($device) {
                $sql->where('oaid', '=', $device['oaid']);
            };
            $filter[$sourceHW][] = function ($sql) use ($device) {
                $sql->where('oaid', '=', $device['oaid']);
            };
            $filter[$sourceVI][] = function ($sql) use ($device) {
                $sql->where('oaid', '=', $device['oaid']);
            };
            $filter[$sourceBD][] = function ($sql) use ($device) {
                $sql->where('oaid', '=', $device['oaid']);
            };
            $filter[$sourceHONOR][] = function ($sql) use ($device) {
                $sql->where('oaid', '=', $device['oaid']);
            };
        }

        if (!empty($device['imei'])) {
            $filter[$sourceJl][] = function ($sql) use ($device) {
                $sql->where('imei', '=', md5($device['imei']));
            };
            $filter[$sourceKS][] = function ($sql) use ($device) {
                $sql->where('imei', '=', md5($device['imei']));
            };
            $filter[$sourceOP][] = function ($sql) use ($device) {
                $sql->where('imei', '=', md5($device['imei']));
            };
//            $filter[$sourceHW][] = function ($sql) use ($device) {
//                $sql->where('imei', '=', $device['imei']);
//            };
            $filter[$sourceVI][] = function ($sql) use ($device) {
                $sql->where('imei', '=', md5($device['imei']));
            };
            $filter[$sourceBD][] = function ($sql) use ($device) {
                $sql->where('imei', '=', md5($device['imei']));
            };
        }

        if (!empty($device['android_id'])) {
            $filter[$sourceJl][] = function ($sql) use ($device) {
                $sql->where('android_id', '=', md5($device['android_id']));
            };
            $filter[$sourceKS][] = function ($sql) use ($device) {
                $sql->where('android_id', '=', md5($device['android_id']));
            };
            $filter[$sourceHW][] = function ($sql) use ($device) {
                $sql->where('android_id', '=', md5($device['android_id']));
            };
            $filter[$sourceVI][] = function ($sql) use ($device) {
                $sql->where('android_id', '=', md5($device['android_id']));
            };
            $filter[$sourceBD][] = function ($sql) use ($device) {
                $sql->where('android_id', '=', md5($device['android_id']));
            };
        }

        // IP+UA|暂未启用
        if (!empty($device['ip']) && !empty($device['ua'])) {
            $filter[$sourceJl][] = function ($sql) use ($device) {
                $sql->where('ip', '=', $device['ip'])
                    ->where('ua', '=', $device['ua']);
            };
            $filter[$sourceKS][] = function ($sql) use ($device) {
                $sql->where('ip', '=', $device['ip'])
                    ->where('ua', '=', $device['ua']);
            };
            $filter[$sourceHW][] = function ($sql) use ($device) {
                $sql->where('ip', '=', $device['ip'])
                    ->where('ua', '=', $device['ua']);
            };
            $filter[$sourceVI][] = function ($sql) use ($device) {
                $sql->where('ip', '=', $device['ip'])
                    ->where('ua', '=', $device['ua']);
            };
            $filter[$sourceBD][] = function ($sql) use ($device) {
                $sql->where('ip', '=', $device['ip'])
                    ->where('ua', '=', $device['ua']);
            };
        }

        foreach ($filter as $source => $where) {
            $tableAd = App::tableAdBySource($appcode, $source);
            if (!Schema::hasTable($tableAd) || empty($where)) {
                continue;
            }
            $ad = DB::table($tableAd)
                ->select(['id', 'account_id', 'aid'])
                ->where('ts', '>', strtotime('-' . self::$maxDays . ' days'))
                ->where('device_id', '=', '')
                ->where(function ($sql) use ($where) {
                    foreach ($where as $v) {
                        $sql->orWhere($v);
                    }
                })
                ->orderBy('ts', 'desc')
                ->first();
            if (!empty($ad)) {
                $ad['source'] = $source;
                break;
            }
        }

        $currTime = time();
        $appInfo = App::getAppByCode($appcode);
        $attr = self::attribution($device['android_id'], $appInfo['package_name'], $appcode);
        if ($attr === false) {
            return $ad;
        }

        if (empty($ad) && empty($attr)) {
            return [];
        }

        $ad_ts = $ad['ts'] ?? 0;
        if (!empty($attr) && $attr['last_touch_time'] > $ad_ts) {
            self::newDBWrite($appcode, $device, $ad, $attr, $currTime);
            $sourceJl = AdConst::PUT_SOURCE_MAP[AdConst::PUT_SOURCE_JL];
            $ad['source'] = $sourceJl;
            $ad['account_id'] = $attr['advertiser_id'];
        } elseif (empty($ad)) {
            return [];
        } else {
            self::origDBWrite($appcode, $device, $ad, $currTime);
        }

        // 判断激活定义
        $account = Account::info($ad['account_id'] ?? 0);
        // 归因激活
        if (($account['active_type'] ?? 2) == 2) {
            dispatch((new DeviceActiveJob([
                'app_code' => $appcode,
                'device_id' => $device['android_id'],
                'active_time' => $currTime,
            ]))->onQueue('{device_active}'));
        } elseif (($account['active_type'] ?? 2) == 4) {
            dispatch((new DeviceActiveCustomJob([
                'app_code' => $appcode,
                'device_id' => $device['android_id'],
            ]))->onQueue('{device_active}'));
        }

        return (array)$ad;
    }

    public static function newDBWrite($appcode, $device, $ad, $attr,
                                      $currTime) {
        $sourceJl = AdConst::PUT_SOURCE_MAP[AdConst::PUT_SOURCE_JL];
        $tableAd = App::tableAdBySource($appcode, $sourceJl);

        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'args' => func_get_args(),
        ]);

        // 归因成功标记
        DB::beginTransaction();

        $adData = [
            'account_id' =>  $attr['advertiser_id'],
            'android_id' =>  $attr['adv_android_id'],
            'campaign_id' =>  $attr['project_id'],
            'campaign_name' =>  $attr['project_name'],
            'aid' =>  $attr['project_id'] . AdConst::PUT_PROJECT_AD_DELIMITER . $attr['promotion_id'],//$attr['aid'],
            'ts' => $attr['last_touch_time'],
            'aid_name' =>  $attr['promotion_name'],
            'cid' =>  $attr['cid'],
            'cid_name' =>  $attr['cid_name'],
//            'csite' =>  $attr[''],
            'request_id' =>  $attr['req_id'],
            'callback_url' => $attr['callback_url'],
            'device_id' => $device['android_id'] ?? '',
            'channel' => $device['channel'] ?? 0,
            'vendor' => $device['vendor'] ?? '',
            'update_time' => $currTime,
        ];

        if (empty($ad)) {
            try {
                $result2 = $adId = DB::table($tableAd)->insertGetId($adData);
            } catch (Exception $e) {
                Log::info(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
            }
        } else {
            $adId = $ad['id'];
            $result2 = DB::table($tableAd)
                ->where('id', $ad['id'] ?? 0)
                ->where('active_status', 0)
                ->update($adData);
        }

        $result1 = DB::table(App::tableDevice($appcode))
            ->where('android_id', $device['android_id'])
            ->where('ad_time', 0)
            ->update([
                'source' => $sourceJl,
                'ad_id' => $adId,
                'account_id' => $adData['account_id'] ?? '',
                'aid' => $adData['aid'] ?? '',
                'ad_time' => $currTime,
                'update_time' => $currTime
            ]);

        if (!$result1 || !$result2) {
            DB::rollBack();
            return [];
        }

        DB::commit();
    }

    public static function origDBWrite($appcode, $device, $ad, $currTime) {
        // 归因成功标记
        DB::beginTransaction();

        $result1 = DB::table(App::tableDevice($appcode))
            ->where('android_id', $device['android_id'])
            ->where('ad_time', 0)
            ->update([
                'source' => $ad['source'],
                'ad_id' => $ad['id'] ?? 0,
                'account_id' => $ad['account_id'] ?? '',
                'aid' => $ad['aid'] ?? '',
                'ad_time' => $currTime,
                'update_time' => $currTime
            ]);

        $result2 = DB::table(App::tableAdBySource($appcode, $ad['source']))
            ->where('id', $ad['id'] ?? 0)
            ->where('active_status', 0)
            ->update([
                'device_id' => $device['android_id'] ?? '',
                'channel' => $device['channel'] ?? 0,
                'vendor' => $device['vendor'] ?? '',
                'update_time' => $currTime
            ]);

        if (!$result1 || !$result2) {
            DB::rollBack();
            return [];
        }

        DB::commit();
    }

    public static function attribution($android_id, $package_name, $appcode)
    {
        $url = "https://analytics.oceanengine.com/sdk/app/attribution";
        $data = array(
            "platform" => "android",
            "package_name" => $package_name,
            "android_id"=> $android_id,
        );
        $header = array(
            'Content-Type' => 'application/json',
        );

        $result = Helper::request($url, 'POST', json_encode($data), $header);
        // 日志
        Log::info(__CLASS__ . ':' . __FUNCTION__, [
            'args' => func_get_args(),
            'data' => $url,
            'result' => $result
        ]);

        if (!empty($result['result'])) {
            $data = json_decode($result['result'], true);
            $result_code = $data['code'] ?? -1;
            if ($result_code == 0) {
                return $data;
            } elseif ($result_code == 202) {
                // 归因中，请重试
//                dispatch((new GuiyinJob([
//                    'app_code' => $appcode,
//                    'android_id' => $android_id,
//                ]))->delay(3));
                return false;
            }
        }

        return [];
    }
}
