<?php

declare(strict_types=1);

namespace App\Service\Cash;

use App\Constants\AppConst;
use App\Model\App;
use Hyperf\DbConnection\Db;

class DeviceService
{
    protected $mktAdvertiserTable = AppConst::TOOLS_MKT_ADVERTISER_TABLE;

    public function scheduleDeviceToday(?string $beginDay = null)
    {
        $beginDay = $beginDay ?: date("Y-m-d", time());
        $this->scheduleDevice($beginDay);
    }

    public function scheduleDevice(?string $beginDay = null)
    {
        $beginDay = $beginDay ?: date("Y-m-d", strtotime("yesterday"));
        $appCodes = App::whereIn('ad_status', [AppConst::APP_PUT_STATUS_SELF, AppConst::APP_PUT_STATUS_AGENCY])->where('type', AppConst::APP_TYPE_TOOLS)
            ->pluck('app_code')->toArray();
        $this->updateAdvertiser();
        $appCodesFunc = function ($appCode) use ($beginDay) : void {

            $installNum = Db::connection('tools')->table("app_{$appCode} as app")
                ->leftJoin("{$this->mktAdvertiserTable} as ma", 'app.account_id', '=', 'ma.advertiser_id')
                ->where("app.create_time", ">=", strtotime($beginDay))
                ->where("app.create_time", "<", strtotime($beginDay) + 24 * 60 * 60)
                ->select([
                    Db::raw('count(app.android_id) AS `device_num`'),
                    Db::raw("'installed' as type"),
                    Db::raw("'{$beginDay}' as date"),
                    Db::raw("'{$appCode}' as app_code"),
                    'app.version',
                    'app.vendor',
                    'app.channel',
                    'app.os_version',
                    'ma.platform',
                    'ma.launch_type'
                ])
                ->groupBy('app.version', 'app.vendor', 'app.channel', 'app.os_version', 'ma.platform', 'ma.launch_type')
                ->get()
                ->all();

            $deu = Db::connection('tools')->table("app_{$appCode}_active_keep as keep")
                ->leftJoin("app_{$appCode} as app", 'app.android_id', '=', 'keep.device_id')
                ->leftJoin("{$this->mktAdvertiserTable} as ma", 'app.account_id', '=', 'ma.advertiser_id')
                ->where('active_day', date("Ymd", strtotime($beginDay)))
                ->where('is_ad_report', 1)
                ->select([
                    Db::raw('count( DISTINCT(keep.device_id) ) AS `device_num`'),
                    Db::raw("'deu' as type"),
                    Db::raw("'{$beginDay}' as date"),
                    Db::raw("'{$appCode}' as app_code"),
                    'app.version',
                    'app.vendor',
                    'app.channel',
                    'app.os_version',
                    'ma.platform',
                    'ma.launch_type'
                ])
                ->groupBy('app.version', 'app.vendor', 'app.channel', 'app.os_version', 'ma.platform', 'ma.launch_type')
                ->get()
                ->all();

            $active = Db::connection('tools')->table("app_{$appCode} as app")
                ->leftJoin("{$this->mktAdvertiserTable} as ma", 'app.account_id', '=', 'ma.advertiser_id')
                ->where("app.active_time", ">=", strtotime($beginDay))
                ->where("app.active_time", "<", strtotime($beginDay) + 24 * 60 * 60)
                ->select([
                    Db::raw('count(app.android_id) AS `device_num`'),
                    Db::raw("'active' as type"), Db::raw("'{$beginDay}' as date"),
                    Db::raw("'{$appCode}' as app_code"),
                    'app.version',
                    'app.vendor',
                    'app.channel',
                    'app.os_version',
                    'ma.platform',
                    'ma.launch_type'
                ])
                ->groupBy('app.version', 'app.vendor', 'app.channel', 'app.os_version', 'ma.platform', 'ma.launch_type')
                ->get()
                ->all();

            $deu_old = Db::connection('tools')->table("app_{$appCode}_active_keep as keep")
                ->leftJoin("app_{$appCode} as app", 'app.android_id', '=', 'keep.device_id')
                ->leftJoin("{$this->mktAdvertiserTable} as ma", 'app.account_id', '=', 'ma.advertiser_id')
                ->where('active_day', date("Ymd", strtotime($beginDay)))
                ->where('is_ad_report', 1)
                ->where("app.create_time", "<", strtotime($beginDay))
                ->select([
                    Db::raw('count( DISTINCT(keep.device_id) ) AS `device_num`'),
                    Db::raw("'deu_old' as type"), Db::raw("'{$beginDay}' as date"),
                    Db::raw("'{$appCode}' as app_code"),
                    'app.version',
                    'app.vendor',
                    'app.channel',
                    'app.os_version',
                    'ma.platform',
                    'ma.launch_type'
                ])
                ->groupBy('app.version', 'app.vendor', 'app.channel', 'app.os_version', 'ma.platform', 'ma.launch_type')->get()->all();

            $dau = Db::connection('tools')->table("app_{$appCode}_active_keep as keep")
                ->leftJoin("app_{$appCode} as app", 'app.android_id', '=', 'keep.device_id')
                ->leftJoin("{$this->mktAdvertiserTable} as ma", 'app.account_id', '=', 'ma.advertiser_id')
                ->where('active_day', date("Ymd", strtotime($beginDay)))
                ->select([
                    Db::raw('count( DISTINCT(keep.device_id) ) AS `device_num`'),
                    Db::raw("'dau' as type"), Db::raw("'{$beginDay}' as date"),
                    Db::raw("'{$appCode}' as app_code"),
                    'app.version',
                    'app.vendor',
                    'app.channel',
                    'app.os_version',
                    'ma.platform',
                    'ma.launch_type'
                ])
                ->groupBy('app.version', 'app.vendor', 'app.channel', 'app.os_version', 'ma.platform', 'ma.launch_type')
                ->get()
                ->all();

            $data = collect($installNum)->merge($deu)->merge($active)->merge($deu_old)->merge($dau)->map(function ($item) {
                data_set($item, 'version', data_get($item, 'version', ''));
                data_set($item, 'vendor', data_get($item, 'vendor', ''));
                data_set($item, 'channel', data_get($item, 'channel', ''));
                data_set($item, 'os_version', data_get($item, 'os_version', ''));
                data_set($item, 'platform', data_get($item, 'platform', ''));
                data_set($item, 'launch_type', data_get($item, 'launch_type', -1));
                return $item;
            })->all();
            $data = object_to_arr($data);
            Db::transaction(function () use ($data, $appCode, $beginDay) {
                Db::table('app_middle_device')->where('app_code', $appCode)->where('date', $beginDay)->delete();
                $data = collect($data)->chunk(500)->toArray();
                foreach ($data as $v) {
                    Db::table('app_middle_device')->insert($v);
                }
            });
        };
        array_map_safe($appCodesFunc, $appCodes);
    }

    /**
     * 同步广告主信息
     */
    public function updateAdvertiser()
    {
        $list = Db::table('mkt_advertiser')->get()->map(function ($item) {
            return (array)$item;
        })->toArray();

        foreach ($list as $item) {
            Db::connection('tools')->table($this->mktAdvertiserTable)->updateOrInsert([
                'advertiser_id' => $item['advertiser_id'],
            ], $item);
        }
    }
}