<?php

declare(strict_types=1);

namespace App\Service\Cash;

use App\Constants\AppConst;
use App\Model\App;
use Hyperf\DbConnection\Db;

class EcpmService
{
    protected $table = 'app_daily_ecpm';

    protected $mktAdvertiserTable = AppConst::TOOLS_MKT_ADVERTISER_TABLE;

    public function ecpmList(string $appCode, array $params)
    {
        $list = Db::table($this->table)->where('app_code', $appCode)
            ->when(($dateEnd = data_get($params, 'date_end')) && ($dateBegin = data_get($params, 'date_begin')), function ($query) use ($dateBegin, $dateEnd) {
                $query->where('date', '>=', $dateBegin)->where('date', '<=', $dateEnd);
            })
            ->when($version = data_get($params, 'version'), function ($query) use ($version) {
                $query->where('version', $version);
            })
            ->when($channel = data_get($params, 'channel'), function ($query) use ($channel) {
                $query->where('channel', $channel);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('platform', $platform);
            })
            ->when(in_array($launchType = data_get($params, 'launch_type'), ['0', '1']), function ($query) use ($launchType) {
                $query->where('launch_type', $launchType);
            })
            ->when($vendor = data_get($params, 'vendor'), function ($query) use ($vendor) {
                switch ($vendor) {
                    case 'huawei':
                        $query->whereIn('vendor', ['huawei', 'honor']);
                        break;
                    case 'oppo':
                        $query->whereIn('vendor', ['oppo', 'realme']);
                        break;
                    case 'vivo':
                        $query->where('vendor', 'vivo');
                        break;
                    case 'xiaomi':
                        $query->whereIn('vendor', ['xiaomi', 'redmi']);
                        break;
                    default:
                        $query->whereNotIn('vendor', ['huawei', 'honor', 'xiaomi', 'redmi', 'oppo', 'realme', 'vivo']);
                }
            })
            ->when($adType = data_get($params, 'ad_type'), function ($query) use ($adType) {
                if ($adType == 'full') {
                    $query->whereIn('ad_type', ['inter_full', 'full_video']);
                } elseif ($adType == 'reword_video') {
                    $query->whereIn('ad_type', ['reword_video', 'reword']);
                } else {
                    $query->where('ad_type', $adType);
                }

            })->get()->all();
        $installed = Db::table('app_middle_device')->select([
            Db::raw('SUM(device_num) AS `device_num`'), 'date', 'type'
        ])->where('app_code', $appCode)
            ->when(($dateEnd = data_get($params, 'date_end')) && ($dateBegin = data_get($params, 'date_begin')), function ($query) use ($dateBegin, $dateEnd) {
                $query->where('date', '>=', $dateBegin)->where('date', '<=', $dateEnd);
            })
            ->whereIn('type', ['installed', 'deu'])
            ->when($version = data_get($params, 'version'), function ($query) use ($version) {
                $query->where('version', $version);
            })
            ->when($channel = data_get($params, 'channel'), function ($query) use ($channel) {
                $query->where('channel', $channel);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('platform', $platform);
            })
            ->when(in_array($launchType = data_get($params, 'launch_type'), ['0', '1']), function ($query) use ($launchType) {
                $query->where('launch_type', $launchType);
            })
            ->when($vendor = data_get($params, 'vendor'), function ($query) use ($vendor) {
                switch ($vendor) {
                    case 'huawei':
                        $query->whereIn('vendor', ['huawei', 'honor']);
                        break;
                    case 'oppo':
                        $query->whereIn('vendor', ['oppo', 'realme']);
                        break;
                    case 'vivo':
                        $query->where('vendor', 'vivo');
                        break;
                    case 'xiaomi':
                        $query->whereIn('vendor', ['xiaomi', 'redmi']);
                        break;
                    default:
                        $query->whereNotIn('vendor', ['huawei', 'honor', 'xiaomi', 'redmi', 'oppo', 'realme', 'vivo']);
                }
            })->groupBy('date', 'type')->get()->all();
        $result = [];
        $ecpmFunc = function ($revenue, $show) {
            $avgRevenue = $show > 0 ? $revenue / $show : 0;
            return round($avgRevenue * 1000, 2);
        };
        for ($beginDate = strtotime(data_get($params, 'date_begin')); $beginDate <= strtotime(data_get($params, 'date_end'));) {
            $day = date('Y-m-d', $beginDate);
            $dataFilterDay = collect($list)->where('date', $day)->all();
            $deu = collect($installed)->where('date', $day)->where('type', 'deu')->pluck('device_num')->first();
            $installNum = collect($installed)->where('date', $day)->where('type', 'installed')->pluck('device_num')->first();

            $result[] = [
                'date' => $day,
                'deu' => $deu,
                'deu_ecpm' => $ecpmFunc(collect($dataFilterDay)->sum('deu_revenue'), collect($dataFilterDay)->sum('deu_show')),
                'installed_num' => $installNum,
                'installed_ecpm' => $ecpmFunc(collect($dataFilterDay)->sum('installed_revenue'), collect($dataFilterDay)->sum('installed_show')),
                'installed_ecpm_1' => $ecpmFunc(collect($dataFilterDay)->sum('installed_revenue_1'), collect($dataFilterDay)->sum('installed_show_1')),
                'installed_ecpm_2' => $ecpmFunc(collect($dataFilterDay)->sum('installed_revenue_2'), collect($dataFilterDay)->sum('installed_show_2')),
                'installed_ecpm_3' => $ecpmFunc(collect($dataFilterDay)->sum('installed_revenue_3'), collect($dataFilterDay)->sum('installed_show_3')),
                'installed_ecpm_4' => $ecpmFunc(collect($dataFilterDay)->sum('installed_revenue_4'), collect($dataFilterDay)->sum('installed_show_4')),
                'installed_ecpm_5' => $ecpmFunc(collect($dataFilterDay)->sum('installed_revenue_5'), collect($dataFilterDay)->sum('installed_show_5')),
                'installed_ecpm_6' => $ecpmFunc(collect($dataFilterDay)->sum('installed_revenue_6'), collect($dataFilterDay)->sum('installed_show_6')),
                'installed_ecpm_7' => $ecpmFunc(collect($dataFilterDay)->sum('installed_revenue_7'), collect($dataFilterDay)->sum('installed_show_7')),
                'installed_ecpm_14' => $ecpmFunc(collect($dataFilterDay)->sum('installed_revenue_14'), collect($dataFilterDay)->sum('installed_show_14')),
                'installed_ecpm_30' => $ecpmFunc(collect($dataFilterDay)->sum('installed_revenue_30'), collect($dataFilterDay)->sum('installed_show_30')),
            ];
            $beginDate += 24 * 60 * 60;
        }
        return collect($result)->sortByDesc('date')->values()->toArray();
    }

    public function scheduleEcpmToday()
    {
        $this->scheduleEcpm(date("Y-m-d"));
    }

    public function scheduleEcpmYesterday()
    {
        $this->scheduleEcpm(date("Y-m-d", strtotime("yesterday")));
    }

    public function scheduleEcpm(string $doDate)
    {
        $appCodes = App::whereIn('ad_status', [AppConst::APP_PUT_STATUS_SELF, AppConst::APP_PUT_STATUS_AGENCY])->where('type', AppConst::APP_TYPE_TOOLS)
            ->pluck('app_code')->toArray();
        $doDate = date('Y-m-d', strtotime($doDate));
        $appCodesFunc = function ($appCode) use ($doDate) : void {
            $this->scheduleDeu($appCode, $doDate);
            $this->scheduleInstalled($appCode, $doDate);
        };
        array_map_safe($appCodesFunc, $appCodes);
    }

    protected function scheduleDeu(string $appCode, string $doDate)
    {
        $temp = strtotime($doDate) < strtotime(date('Y-m-d 00:00:00', strtotime('-1 day'))) ? '_temp' : '';
        $doDateTimestamp = strtotime($doDate);

        $list = Db::connection('tools')->table("app_{$appCode}_event_ad_show{$temp} as appshow")
            ->leftJoin("app_{$appCode} as app", 'app.android_id', '=', 'appshow.android_id')
            ->leftJoin("{$this->mktAdvertiserTable} as ma", 'ma.advertiser_id', '=', 'app.account_id')
            ->where("appshow.create_time", ">=", $doDateTimestamp)
            ->where("appshow.create_time", "<", $doDateTimestamp + 86400)
            ->select([
                Db::raw("COUNT(appshow.uuid) AS deu_show"),
                Db::raw("SUM(appshow.info->>'$.realEcpm')/1000 AS deu_revenue"),
                Db::raw("appshow.info->>'$.adType' as ad_type"),
                "appshow.vendor",
                "appshow.channel",
                "appshow.version",
                'ma.platform',
                'ma.launch_type'
            ])
            ->groupBy('ad_type', 'appshow.vendor', 'appshow.channel', 'appshow.version', 'ma.platform', 'ma.launch_type')
            ->get()
            ->all();
        $this->scheduleSave($appCode, $doDate, $list, 'deu');
    }

    public function scheduleInstalled(string $appCode, string $doDate)
    {
        $doDateTimestamp = strtotime($doDate);
        $temp = $doDateTimestamp < strtotime(date('Y-m-d 00:00:00', strtotime('-1 day'))) ? '_temp' : '';

        $list = Db::connection('tools')->table("app_{$appCode}_event_ad_show{$temp} as appshow")
            ->leftjoin("app_{$appCode} as app", "app.android_id", '=', "appshow.android_id")
            ->leftJoin("{$this->mktAdvertiserTable} as ma", 'ma.advertiser_id', '=', 'app.account_id')
            ->where("appshow.create_time", ">=", $doDateTimestamp)
            ->where("appshow.create_time", "<", $doDateTimestamp + 86400)
            ->select([
                Db::raw("COUNT(appshow.uuid) AS installed_show"),
                Db::raw("SUM(appshow.info->>'$.realEcpm')/1000 AS installed_revenue"),
                Db::raw("appshow.info->>'$.adType' as ad_type"),
                "appshow.vendor",
                "appshow.channel",
                "appshow.version",
                'ma.platform',
                'ma.launch_type',
                Db::raw("ceil({$doDateTimestamp}/86400 - app.create_time/86400) as day"),
            ])
            ->groupBy('day', 'ad_type', 'appshow.vendor', 'appshow.channel', 'appshow.version', 'ma.platform', 'ma.launch_type')
            ->havingRaw('day IN (0,1,2,3,4,5,6,7,14,30)')
            ->get()
            ->all();
        $this->scheduleSave($appCode, $doDate, $list, 'installed');
    }

    protected function scheduleSave($appCode, $date, $list, $type)
    {
        foreach ($list as $item) {
            $day = data_get($item, 'day', 0);
            $attributes = [
                'app_code' => $appCode,
                'date' => date('Y-m-d', (strtotime($date) - $day * 86400)),
                'version' => data_get($item, 'version', ''),
                'channel' => data_get($item, 'channel', ''),
                'vendor' => data_get($item, 'vendor', ''),
                'ad_type' => data_get($item, 'ad_type', ''),
                'platform' => data_get($item, 'platform', ''),
                'launch_type' => data_get($item, 'launch_type', -1),
            ];
            $fieldShow = $type . '_show';
            $fieldRevenue = $type . '_revenue';
            $dayStr = $day > 0 ? ('_' . $day) : '';
            $values = [
                $fieldShow . $dayStr => data_get($item, $fieldShow),
                $fieldRevenue . $dayStr => data_get($item, $fieldRevenue),
            ];

            Db::table($this->table)->updateOrInsert($attributes, $values);
        }
    }
}