<?php

declare(strict_types=1);

namespace App\Service\Cash;

use App\Constants\AppConst;
use App\Model\App;
use Hyperf\DbConnection\Db;

class RevenueService
{
    protected $mktAdvertiserTable = AppConst::TOOLS_MKT_ADVERTISER_TABLE;

    public function scheduleRevenueToday()
    {
        $this->scheduleRevenue(date("Y-m-d"));
    }

    public function scheduleRevenueYesterday()
    {
        $this->scheduleRevenue(date("Y-m-d", strtotime("yesterday")));
    }

    public function scheduleRevenue(string $doDate)
    {
        $appCodes = App::whereIn('ad_status', [AppConst::APP_PUT_STATUS_SELF])->where('type', AppConst::APP_TYPE_TOOLS)
            ->pluck('app_code')->toArray();
        if ($doDate == date("Y-m-d")) {
            $days = [0];
        } else {
            $days = [0, 1, 2, 3, 4, 5, 6, 7, 14, 30];
        }

        $daysFunc = function ($day) use ($appCodes, $doDate) : void {
            $appCodesFunc = function ($appCode) use ($doDate, $day) : void {
                if ($day == 0) {
//                    $saveData = [];
//                    $saveData['cost'] = Db::table('report_daily_advertiser')->where('app_code', $appCode)
//                        ->where('date', $doDate)->sum('cost');
//                    $saveData['installed_num'] = Db::connection('tools')->table("app_{$appCode} as app")
//                        ->where('app.ad_id', '>', 0)
//                        ->where('app.create_time', '>=', strtotime($doDate))
//                        ->where('app.create_time', '<', strtotime($doDate) + 24 * 60 * 60)->count();
//                    Db::table('app_daily_revenue')->updateOrInsert(['app_code' => $appCode, 'date' => $doDate], $saveData);

                    $cost = Db::table('report_daily_advertiser as rda')
                        ->leftJoin('mkt_advertiser as ma', 'ma.advertiser_id', '=', 'rda.advertiser_id')
                        ->select(Db::raw('SUM(rda.cost) as cost'), 'ma.platform', 'ma.launch_type')
                        ->where('rda.app_code', $appCode)
                        ->where('rda.date', $doDate)
                        ->groupBy('ma.platform', 'ma.launch_type')
                        ->get()
                        ->map(function ($item) {
                            return object_to_arr($item);
                        })
                        ->toArray();
                    $install = Db::connection('tools')->table("app_{$appCode} as app")
                        ->leftJoin("{$this->mktAdvertiserTable} as ma", 'ma.advertiser_id', '=', 'app.account_id')
                        ->select(Db::raw('count(app.id) as install'), 'ma.platform', 'ma.launch_type')
                        ->where('app.ad_id', '>', 0)
                        ->where('app.create_time', '>=', strtotime($doDate))
                        ->where('app.create_time', '<', strtotime($doDate) + 24 * 60 * 60)
                        ->groupBy('platform', 'launch_type')
                        ->get()
                        ->map(function ($item) {
                            return object_to_arr($item);
                        })
                        ->toArray();
                    $list = [];
                    foreach ($cost as $v) {
                        $platform = $v['platform'] ?? '';
                        $launchType = $v['launch_type'] ?? -1;
                        $list[$platform][$launchType]['cost'] = $v['cost'];
                    }
                    foreach ($install as $v) {
                        $platform = $v['platform'] ?? '';
                        $launchType = $v['launch_type'] ?? -1;
                        $list[$platform][$launchType]['installed_num'] = $v['install'];
                    }

                    foreach ($list as $platform => $launchList) {
                        foreach ($launchList as $launchType => $data) {
                            Db::table('app_daily_revenue')->updateOrInsert([
                                'app_code' => $appCode,
                                'date' => $doDate,
                                'platform' => $platform,
                                'launch_type' => $launchType,
                            ], $data);
                        }
                    }
                }
                $this->scheduleKeepRevenue($appCode, $doDate, $day);
            };
            array_map_safe($appCodesFunc, $appCodes);
        };
        array_map_safe($daysFunc, $days);
    }

    protected function scheduleKeepRevenue(string $appCode, string $doDate, int $day)
    {
        $column = 'installed_revenue';
        if ($day != 0) {
            $column .= "_{$day}";
        }

        $temp = strtotime($doDate) < strtotime(date('Y-m-d 00:00:00', strtotime('-1 day'))) ? '_temp' : '';

//        $revenue = Db::connection('tools')->table("app_{$appCode}_event_ad_revenue{$temp} as eig")
//            ->where('eig.time', '>=', date('YmdHis', strtotime($doDate)))
//            ->where('eig.time', '<', date('YmdHis', strtotime($doDate) + 24 * 60 * 60))
//            ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
//            ->where('app.ad_id', '>', 0)
//            ->where("app.create_time", ">=", strtotime($doDate) - 24 * 60 * 60 * $day)
//            ->where("app.create_time", "<", strtotime($doDate) + 24 * 60 * 60 - 24 * 60 * 60 * $day)
//            ->sum("eig.amount");
//        Db::table('app_daily_revenue')->updateOrInsert(
//            ['app_code' => $appCode, 'date' => date("Y-m-d", strtotime($doDate) - 24 * 60 * 60 * $day)],
//            [$column => $revenue]
//        );

        $revenueList = Db::connection('tools')->table("app_{$appCode}_event_ad_revenue{$temp} as eig")
            ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
            ->leftJoin("{$this->mktAdvertiserTable} as ma", "ma.advertiser_id", "app.account_id")
            ->where('eig.time', '>=', date('YmdHis', strtotime($doDate)))
            ->where('eig.time', '<', date('YmdHis', strtotime($doDate) + 24 * 60 * 60))
            ->where('app.ad_id', '>', 0)
            ->where("app.create_time", ">=", strtotime($doDate) - 24 * 60 * 60 * $day)
            ->where("app.create_time", "<", strtotime($doDate) + 24 * 60 * 60 - 24 * 60 * 60 * $day)
            ->select([
                Db::raw("SUM(eig.amount) as amount"),
                'ma.platform',
                'ma.launch_type'
            ])
            ->groupBy('ma.platform', 'ma.launch_type')
            ->get()
            ->map(function ($item) {
                return object_to_arr($item);
            })
            ->toArray();

        foreach ($revenueList as $v) {
            Db::table('app_daily_revenue')->updateOrInsert([
                'app_code' => $appCode,
                'date' => date("Y-m-d", strtotime($doDate) - 24 * 60 * 60 * $day),
                'platform' => $v['platform'] ?? '',
                'launch_type' => $v['launch_type'] ?? -1,
            ], [$column => $v['amount']]);
        }
    }

    public function revenueList(string $appCode, array $params)
    {
        return Db::table('app_daily_revenue')
            ->where('app_code', $appCode)
            ->when(($dateEnd = data_get($params, 'date_end')) && ($dateBegin = data_get($params, 'date_begin')), function ($query) use ($dateBegin, $dateEnd) {
                $query->where('date', '>=', $dateBegin)->where('date', '<=', $dateEnd);
            })
            ->when($platform = data_get($params, 'platform', ''), function ($query) use ($platform) {
                $query->where('platform', $platform);
            })
            ->when(in_array($launchType = data_get($params, 'launch_type'), ['0', '1']), function ($query) use ($launchType) {
                $query->where('launch_type', $launchType);
            })
            ->select([
                'date',
                Db::raw('SUM(cost) as cost'),
                Db::raw('SUM(installed_num) as installed_num'),
                Db::raw('SUM(installed_revenue) as installed_revenue'),
                Db::raw('SUM(installed_revenue_1) as installed_revenue_1'),
                Db::raw('SUM(installed_revenue_2) as installed_revenue_2'),
                Db::raw('SUM(installed_revenue_3) as installed_revenue_3'),
                Db::raw('SUM(installed_revenue_4) as installed_revenue_4'),
                Db::raw('SUM(installed_revenue_5) as installed_revenue_5'),
                Db::raw('SUM(installed_revenue_6) as installed_revenue_6'),
                Db::raw('SUM(installed_revenue_7) as installed_revenue_7'),
                Db::raw('SUM(installed_revenue_14) as installed_revenue_14'),
                Db::raw('SUM(installed_revenue_30) as installed_revenue_30'),
            ])
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)));
    }
}