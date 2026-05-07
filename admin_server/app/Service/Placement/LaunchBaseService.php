<?php

declare(strict_types=1);

namespace App\Service\Placement;

use App\Constants\AppConst;
use App\Constants\MediaConst;
use App\Constants\UserConst;
use App\Library\Aliyun\Oss;
use App\Library\Transverter\Transverter;
use App\Model\App;
use App\Model\ReportDailyLaunchBase;
use App\Service\AppService;
use App\Service\UserService;
use App\Utils\Ding;
use App\Utils\Lark;
use Hyperf\Context\Context;
use Hyperf\DbConnection\Db;

class LaunchBaseService
{
    public function schedule()
    {
        //ad_status 代投产品 type 工具产品
        $appCodes = App::whereIn('ad_status', [AppConst::APP_PUT_STATUS_SELF, AppConst::APP_PUT_STATUS_AGENCY])->where('type', AppConst::APP_TYPE_TOOLS)
            ->pluck('app_code')->toArray();
        array_map_safe($this->scheduleYesterday(), $appCodes);
        array_map_safe($this->scheduleBeforeYesterday(), $appCodes);
    }

    public function scheduleRevenue(string $date = null)
    {
        $appCodes = App::whereIn('ad_status', [AppConst::APP_PUT_STATUS_SELF, AppConst::APP_PUT_STATUS_AGENCY])->where('type', AppConst::APP_TYPE_TOOLS)
            ->pluck('app_code')->toArray();
        array_map_safe($this->scheduleMediaRevenue($date), $appCodes);
    }

    protected function scheduleMediaRevenue(string $date = null)
    {
        $date = $date ?: date("Y-m-d", time() - 24 * 60 * 60);
        return function (string $appCode) use ($date): void {
            ReportDailyLaunchBase::updateOrCreate([
                'created_date' => $date,
                'app_code' => $appCode
            ], $this->mediaInfo($appCode, $date));
        };
    }

    protected function scheduleBeforeYesterday()
    {
        return function (string $appCode): void {
            $currentDate = date("Y-m-d", time() - 24 * 60 * 60 * 2);
            $launchModel = Db::table('report_daily_advertiser')->where('app_code', $appCode)->where('date', $currentDate);
            $mediaInfo = $this->mediaInfo($appCode, $currentDate);
            $data = [
                'next_day_open' => $launchModel->sum('next_day_open'),
                'increment_next_day_open' => Db::connection('tools')->table("app_{$appCode}_active_keep")
                    ->where('active_day', date("Ymd", strtotime($currentDate) + 24 * 60 * 60))
                    ->where('install_day', date("Ymd", strtotime($currentDate)))->count(),
                'media_revenue' => $mediaInfo['media_revenue'],
                'media_detail' => $mediaInfo['media_detail'],
            ];
            ReportDailyLaunchBase::updateOrCreate([
                'created_date' => $currentDate,
                'app_code' => $appCode
            ], $data);
        };
    }

    public function scheduleYesterday()
    {
        return function (string $appCode): void {
            $beginDate = date("Y-m-d", strtotime("yesterday"));
            $beginTime = strtotime($beginDate);
            $endTime = $beginTime + 24 * 60 * 60 - 1;

            $launchModel = Db::table('report_daily_advertiser')->where('app_code', $appCode)->where('date', $beginDate);
            $appDeviceModel = Db::connection('tools')->table("app_{$appCode} as app")
                ->whereBetween('app.create_time', [$beginTime, $endTime]);
            $incrementShow = Db::connection('tools')->table("app_{$appCode}_event_ad_show as show")
                ->whereBetween('show.create_time', [$beginTime, $endTime])
                ->leftJoin("app_{$appCode} as app", "app.android_id", "show.android_id")
                ->whereBetween('app.create_time', [$beginTime, $endTime])
                ->sum("show.times");
            $show = Db::connection('tools')->table("app_{$appCode}_event_ad_show")
                ->whereBetween('create_time', [$beginTime, $endTime])
                ->sum("times");
            $imputedRevenue = Db::connection('tools')->table("app_{$appCode}_event_ad_revenue as eig")
                ->where('eig.time', '>=', date('YmdHis', $beginTime))
                ->where('eig.time', '<=', date('YmdHis', $endTime))
                ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
                ->whereBetween('app.create_time', [$beginTime, $endTime])
                ->sum("eig.amount");
            $activeRevenue = Db::connection('tools')->table("app_{$appCode}_event_ad_revenue as eig")
                ->where('eig.time', '>=', date('YmdHis', $beginTime))
                ->where('eig.time', '<=', date('YmdHis', $endTime))
                ->sum("eig.amount");

            $versionInstall = (clone $appDeviceModel)->select(['app.version', Db::raw('count(app.android_id) AS device_num'),])
                ->whereNotExists(function ($query) use ($appCode) {
                    $query->select(DB::raw(1))
                        ->from("app_{$appCode}_temp as apptemp")
                        ->whereRaw('app.android_id = apptemp.android_id');
                })
                ->groupBy('app.version')->get()
                ->filter(function ($item) {
                    return data_get($item, 'device_num') > 100;
                })
                ->sortByDesc(function ($item) {
                    $version = data_get($item, 'version');
                    $numArr = explode('.', $version);
                    $num = 0;
                    foreach ($numArr as $key => $val) {
                        $num += $val << ((count($numArr) - $key - 1) * 10);
                    }
                    return $num;
                })->take(2)->values();
            $versionGuiyin = (clone $appDeviceModel)
                ->whereNotExists(function ($query) use ($appCode) {
                    $query->select(DB::raw(1))
                        ->from("app_{$appCode}_temp as apptemp")
                        ->whereRaw('app.android_id = apptemp.android_id');
                })->where('app.ad_time', '>', 0)
                ->whereIn('app.version', $versionInstall->pluck('version')->toArray())->count();
            $mediaInfo = $this->mediaInfo($appCode, $beginDate);
            $data = [
                'created_date' => $beginDate,
                'app_code' => $appCode,
                'cost' => $launchModel->sum('cost'),
                'active' => $launchModel->sum('active'),
                'convert' => $launchModel->sum('convert'),
                'next_day_open' => $launchModel->sum('next_day_open'),
                'increment_install' => (clone $appDeviceModel)->count(),
                'version_install' => $versionInstall->sum('device_num'),
                'version_guiyin' => $versionGuiyin,
                'guiyin_success' => (clone $appDeviceModel)->where('app.ad_time', '>', 0)->count(),
                'guiyin_success_new' => (clone $appDeviceModel)->whereNotExists(function ($query) use ($appCode) {
                    $query->select(DB::raw(1))
                        ->from("app_{$appCode}_temp as apptemp")
                        ->whereRaw('app.android_id = apptemp.android_id');
                })->where('app.ad_time', '>', 0)->count(),
                'increment_install_new' => (clone $appDeviceModel)->whereNotExists(function ($query) use ($appCode) {
                    $query->select(DB::raw(1))
                        ->from("app_{$appCode}_temp as apptemp")
                        ->whereRaw('app.android_id = apptemp.android_id');
                })->count(),
                'increment_next_day_open' => Db::connection('tools')->table("app_{$appCode}_active_keep")
                    ->where('active_day', date("Ymd", $beginTime + 24 * 60 * 60))
                    ->where('install_day', date("Ymd", $beginTime))->count(),
                'dau' => Db::connection('tools')->table("app_{$appCode}_active_keep")
                    ->where('active_day', date("Ymd", $beginTime))->count(),
                'deu' => Db::connection('tools')->table("app_{$appCode}_active_keep")
                    ->where('active_day', date("Ymd", $beginTime))
                    ->where('is_ad_report', 1)->count(),
                'show' => $show,
                'increment_show' => $incrementShow,
                'install_huawei' => (clone $appDeviceModel)->whereIn('vendor', ['HUAWEI', 'HONOR'])->count(),
                'install_xiaomi' => (clone $appDeviceModel)->whereIn('vendor', ['Xiaomi', 'Redmi'])->count(),
                'install_oppo' => (clone $appDeviceModel)->whereIn('vendor', ['OPPO', 'realme'])->count(),
                'install_vivo' => (clone $appDeviceModel)->where('vendor', 'vivo')->count(),
                'imputed_revenue' => $imputedRevenue,
                'active_revenue' => $activeRevenue,
                'media_revenue' => $mediaInfo['media_revenue'],
                'media_detail' => $mediaInfo['media_detail'],
            ];

            ReportDailyLaunchBase::updateOrCreate([
                'created_date' => $beginDate,
                'app_code' => $appCode
            ], $data);
        };
    }

    public function sendReport($robotCode = 'daily_report')
    {
        if (!is_product()) {
            return;
        }
        //ad_status 代投产品 type 工具产品
        $appCodes = App::where('ad_status', AppConst::APP_PUT_STATUS_SELF)->where('type', AppConst::APP_TYPE_TOOLS)
            ->pluck('app_code')->toArray();
        $endData = date("Y-m-d", strtotime("yesterday"));
        $beginData = date("Y-m-d", strtotime("yesterday") - 24 * 60 * 60 * 14);

        $func = function ($appCode) use ($beginData, $endData, $robotCode): void {
            $records = ReportDailyLaunchBase::where('app_code', $appCode)
                ->whereBetween('created_date', [$beginData, $endData])->orderBy('created_date')->get()->all();
            $appName = App::where('app_code', $appCode)->pluck('app_name')->first();
            $data = [
                'records' => $records,
                'appName' => $appName,
            ];
            $img_path = BASE_PATH . '/runtime/logs/' . uniqid() . '.jpg';
            $res = make(Transverter::class, [$data])->toImage($img_path, 'transerter_layout/launch_base');
            if (!$res) {
                return;
            }
            defer(function () use ($img_path) {
                unlink($img_path);
            });
//            $Oss = make(Oss::class);
//            $ossUrl = $Oss->putPublicBucket($img_path);
//            $message = '**日期：' . date("Y-m-d") . "**\n\n";
//            $message .= "**产品：{$appName}**\n\n";
//            $message .= '![](' . $ossUrl['oss_request_url'] . ')';
//            //daily_report
//            make(Ding::class)
//                ->setRobotCode($robotCode)
//                ->pushMarkdown('日报', $message);
            make(Lark::class)
                ->setRobotCode($robotCode)
                ->pushImage($img_path);
        };
        array_map_safe($func, $appCodes);
    }

    public function list(array $params, string $appCode = null)
    {
        $userId = (int)data_get(Context::get('_userInfo'), 'user_id');
        $userInfo = make(UserService::class)->getUserInfo($userId);
        if (data_get($userInfo, 'is_super') != UserConst::USER_TYPE['super']) {
            $userApp = make(AppService::class)->getUserApp($userId);
            $appCodeTotal = array_keys($userApp);
        }
        $result = ReportDailyLaunchBase::query()
            ->when($appCode, function ($query) use ($appCode) {
                $query->where('app_code', $appCode);
            })
            ->when($appCodeArr = data_get($params, 'app_code'), function ($query) use ($appCodeArr) {
                $query->whereIn('app_code', $appCodeArr);
            })
            ->when($appCodeTotal = $appCodeTotal ?? [], function ($query) use ($appCodeTotal) {
                $query->whereIn('app_code', $appCodeTotal);
            })
            ->when(($dateEnd = data_get($params, 'date_end')) && ($dateBegin = data_get($params, 'date_begin')), function ($query) use ($params) {
                $dateEnd = data_get($params, 'date_end');
                $dateBegin = data_get($params, 'date_begin');
                $query->where('created_date', '>=', $dateBegin)->where('created_date', '<=', $dateEnd);
            })
            ->orderBy('created_date', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)));

        $appList = (new App())->appList();
        collect($result->items())->map(function ($item) use ($appList) {
            $appName = $appList[data_get($item, 'app_code')] ?? '';
            data_set($item, 'app_name', $appName);
            return $item;
        });
        return $result;
    }

    protected function mediaInfo(string $appCode, string $date)
    {

        $media = Db::table('media_income')
            ->where('app_code', $appCode)
            ->where('date', date('Y-m-d', strtotime($date)))
            ->select([
                'media_type',
                Db::raw('sum(revenue) as revenue')
            ])
            ->groupBy('media_type')
            ->get()
            ->map(function ($item) {
                return object_to_arr($item);
            })
            ->toArray();
        $mediaRevenue = array_sum(array_column($media, 'revenue'));
        $mediaDetail = [];
        foreach ($media as $v) {
            $v['media_name'] = MediaConst::MEDIA_TYPE[$v['media_type']] ?? '';
            $mediaDetail[$v['media_type']] = $v;
        }
        return [
            'media_revenue' => $mediaRevenue,
            'media_detail' => $mediaDetail
        ];
    }
}