<?php

declare(strict_types=1);

namespace App\Service\Cash;

use App\Constants\AppConst;
use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\App;
use Hyperf\DbConnection\Db;
use function array_map_safe;
use function collect;
use function data_get;
use function data_set;
use function object_to_arr;

class LiveService
{
    protected $middleActiveTable = 'com_middle_live';
    
    protected $keepDays = 1;

    public function getVersions(array $versions, string $operator = ''): array
    {
        switch ($operator) {
            case 'os_lt_8':
                return collect($versions)->filter(function ($item) {
                    return version_compare($item, '8', '<');
                })->values()->toArray();
            case 'os_ge_8_lt_9':
                return collect($versions)->filter(function ($item) {
                    return version_compare($item, '8', '>=') && version_compare($item, '9', '<');
                })->values()->toArray();
            case 'os_ge_9_lt_10':
                return collect($versions)->filter(function ($item) {
                    return version_compare($item, '9', '>=') && version_compare($item, '10', '<');
                })->values()->toArray();
            case 'os_ge_10_lt_11':
                return collect($versions)->filter(function ($item) {
                    return version_compare($item, '10', '>=') && version_compare($item, '11', '<');
                })->values()->toArray();
            case 'os_ge_11_lt_12':
                return collect($versions)->filter(function ($item) {
                    return version_compare($item, '11', '>=') && version_compare($item, '12', '<');
                })->values()->toArray();
            case 'os_ge_12_lt_13':
                return collect($versions)->filter(function ($item) {
                    return version_compare($item, '12', '>=') && version_compare($item, '13', '<');
                })->values()->toArray();
            case 'os_ge_13_lt_14':
                return collect($versions)->filter(function ($item) {
                    return version_compare($item, '13', '>=') && version_compare($item, '14', '<');
                })->values()->toArray();
            case 'os_ge_14_lt_15':
                return collect($versions)->filter(function ($item) {
                    return version_compare($item, '14', '>=') && version_compare($item, '15', '<');
                })->values()->toArray();
            case 'os_ge_15_lt_16':
                return collect($versions)->filter(function ($item) {
                    return version_compare($item, '15', '>=') && version_compare($item, '16', '<');
                })->values()->toArray();
            case 'os_ge_16':
                return collect($versions)->filter(function ($item) {
                    return version_compare($item, '16', '>=');
                })->values()->toArray();
            default:
                return collect($versions)->values()->toArray();
        }
    }

    public function list(string $appCode, string $type, array $params)
    {
        $appInfo = App::where('app_code', $appCode)->first();
        switch ($appInfo->type) {
            case AppConst::APP_TYPE_MIS:
                throw new BusinessException(ErrorCode::SERVER_ERROR, '网赚数据暂未支持');
                break;
            case AppConst::APP_TYPE_TOOLS:
                return $this->toolsLiveList($appCode, $type, $params);
                break;
            default :
                throw new BusinessException(ErrorCode::SERVER_ERROR, '不存在的业务类型');
        }
    }

    protected function toolsLiveList(string $appCode, string $type, array $params): array
    {
        $data = Db::table('app_middle_live')->where('app_code', $appCode)->where('type', $type)
            ->when(($dateEnd = data_get($params, 'date_end')) && ($dateBegin = data_get($params, 'date_begin')), function ($query) use ($dateBegin, $dateEnd) {
                $query->where('date', '>=', $dateBegin)->where('date', '<=', $dateEnd);
            })
            ->when($version = data_get($params, 'version'), function ($query) use ($version) {
                $query->where('version', $version);
            })
            ->when($channel = data_get($params, 'channel'), function ($query) use ($channel) {
                $query->where('channel', $channel);
            })->get()->map(function ($item) {
                $vendor = data_get($item, 'vendor');
                data_set($item, 'vendor', strtolower($vendor));
                return $item;
            })->all();
        $result = [];
        $vendorStrategy = [
            'huawei' => function ($data) {
                return collect($data)->whereIn('vendor', ['huawei', 'honor'])->all();
            },
            'xiaomi' => function ($data) {
                return collect($data)->whereIn('vendor', ['xiaomi', 'redmi'])->all();
            },
            'oppo' => function ($data) {
                return collect($data)->whereIn('vendor', ['oppo', 'realme'])->all();
            },
            'vivo' => function ($data) {
                return collect($data)->where('vendor', 'vivo')->all();
            },
            'other' => function ($data) {
                return collect($data)->whereNotIn('vendor', ['huawei', 'honor', 'xiaomi', 'redmi', 'oppo', 'realme', 'vivo'])->all();
            },
        ];
        $osVersions = Db::table('app_middle_live')->select('os_version')->where('app_code', $appCode)
            ->groupBy('os_version')->having('os_version', '<>', '')
            ->orderBy('os_version')->get()->pluck('os_version')->values()->toArray();
        $osVersionKeys = ['os_lt_8', 'os_ge_8_lt_9', 'os_ge_9_lt_10', 'os_ge_10_lt_11', 'os_ge_11_lt_12', 'os_ge_12_lt_13', 'os_ge_13_lt_14', 'os_ge_14_lt_15','os_ge_15_lt_16','os_ge_16'];
        for ($beginDate = strtotime(data_get($params, 'date_begin')); $beginDate <= strtotime(data_get($params, 'date_end'));) {
            $day = date('Y-m-d', $beginDate);
            $dataFileDay = collect($data)->where('date', $day)->all();
            $dataVendor = [
                'date' => $day,
                'device_num' => collect($dataFileDay)->sum('device_num'),
            ];
            foreach ($vendorStrategy as $vendorKey => $vendorFunc) {
                $dataFileVendor = $vendorFunc($dataFileDay);
                $dataOsVersion = [
                    'device_num' => collect($dataFileVendor)->sum('device_num'),
                ];
                foreach ($osVersionKeys as $osVersionKey) {
                    $versions = $this->getVersions($osVersions, $osVersionKey);
                    $dataOsVersion[$osVersionKey] = [
                        'time' => collect($vendorFunc($dataFileDay))->whereIn('os_version', $versions)
                            ->sum('live_time'),
                        'num' => collect($vendorFunc($dataFileDay))->whereIn('os_version', $versions)
                            ->sum('device_num')
                    ];
                }
                $dataVendor[$vendorKey] = $dataOsVersion;
            }
            $result[] = $dataVendor;
            $beginDate += 24 * 60 * 60;
        }
        return collect($result)->sortByDesc('date')->values()->toArray();
    }

    public function scheduleLiveToday(?string $beginDay = null)
    {
        $beginDay = $beginDay ?: date("Y-m-d", time());
        $this->scheduleLive($beginDay);
    }

    public function scheduleLive(?string $beginDay = null)
    {
        $beginDay = $beginDay ?: date("Y-m-d", strtotime("yesterday"));
        $appCodes = App::whereIn('ad_status', [AppConst::APP_PUT_STATUS_SELF, AppConst::APP_PUT_STATUS_AGENCY])->where('type', AppConst::APP_TYPE_TOOLS)
            ->pluck('app_code')->toArray();
        $appCodesFunc = function ($appCode) use ($beginDay) : void {
            $activeTable = "app_{$appCode}_active";
            if (strtotime($beginDay) < strtotime(date("Y-m-d", time())) - $this->keepDays * 24 * 60 * 60) {
                $activeTable = "app_{$appCode}_active_temp";
            }
            $data = Db::connection('tools')->table("{$activeTable} as appa")
                ->where('appa.create_time', '>=', strtotime($beginDay))
                ->where('appa.create_time', '<', strtotime($beginDay) + 24 * 60 * 60)
                ->groupBy('appa.android_id')
                ->select([
                    'appa.android_id', Db::raw('SUM(appa.time) AS `timekeeping`'), Db::raw("'{$beginDay}' as active_date"),
                    Db::raw("'{$appCode}' as app_code"), 'appa.version', 'appa.channel', 'appa.os_version', 'appa.vendor'
                ])->get()->all();
            $data = collect($data)->filter(function ($item) {
                $timekeeping = data_get($item, 'timekeeping');
                return $timekeeping <= 24 * 60 * 60 * 1000;
            })->values()->all();
            $data = object_to_arr($data);
            Db::transaction(function () use ($data, $appCode, $beginDay) {
                Db::connection('tools')->table($this->middleActiveTable)->where('app_code', $appCode)->where('active_date', $beginDay)->delete();
                $data = collect($data)->chunk(500)->toArray();
                foreach ($data as $v) {
                    Db::connection('tools')->table($this->middleActiveTable)->insert($v);
                }
            });

            $installedData = Db::connection('tools')->table("app_{$appCode} as app")
                ->leftJoin("{$this->middleActiveTable} as middle", function ($join) use ($beginDay) {
                    $join->on('middle.android_id', '=', 'app.android_id')
                        ->where('middle.active_date', $beginDay);
                })
                ->select([
                    "app.vendor as vendor", "app.os_version as os_version", "app.version as version", "app.channel as channel",
                    Db::raw('SUM(middle.timekeeping) AS `live_time`'), Db::raw('count( DISTINCT ( app.android_id ) ) AS device_num'),
                    Db::raw("'{$appCode}' as app_code"), Db::raw("'{$beginDay}' as date"), Db::raw("'installed' as type")
                ])
                ->where('app.create_time', '>=', strtotime($beginDay))
                ->where('app.create_time', '<', strtotime($beginDay) + 24 * 60 * 60)
                ->where('middle.app_code', $appCode)
                ->groupBy('app.vendor', 'app.os_version', 'app.version', 'app.channel')
                ->get()->all();
            $dauData = Db::connection('tools')->table("{$this->middleActiveTable} as middle")
                ->select([
                    "middle.vendor as vendor", "middle.os_version as os_version", "middle.version as version", "middle.channel as channel",
                    Db::raw('SUM(middle.timekeeping) AS `live_time`'), Db::raw('count( DISTINCT ( middle.android_id ) ) AS device_num'),
                    Db::raw("'{$appCode}' as app_code"), Db::raw("'{$beginDay}' as date"), Db::raw("'dau' as type")
                ])
                ->where('middle.active_date', $beginDay)
                ->where('middle.app_code', $appCode)
                ->groupBy('middle.vendor', 'middle.os_version', 'middle.version', 'middle.channel')
                ->get()->all();
            $data = array_merge(object_to_arr($installedData), object_to_arr($dauData));
            $data = collect($data)->map(function ($item) {
                if (!data_get($item, 'live_time')) {
                    data_set($item, 'live_time', 0);
                }
                return $item;
            })->all();
            $data = object_to_arr($data);
            Db::transaction(function () use ($data, $appCode, $beginDay) {
                Db::table('app_middle_live')->where('app_code', $appCode)->where('date', $beginDay)->delete();
                $data = collect($data)->chunk(500)->toArray();
                foreach ($data as $v) {
                    Db::table('app_middle_live')->insert($v);
                }
                Db::connection('tools')->table($this->middleActiveTable)->where('app_code', $appCode)->where('active_date', $beginDay)->delete();
            });
        };
        array_map_safe($appCodesFunc, $appCodes);
    }
}