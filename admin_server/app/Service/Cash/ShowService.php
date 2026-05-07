<?php

declare(strict_types=1);

namespace App\Service\Cash;

use App\Constants\AppConst;
use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\App;
use Hyperf\DbConnection\Db;

class ShowService
{
    protected $mktAdvertiserTable = AppConst::TOOLS_MKT_ADVERTISER_TABLE;

    public function getVersions(string $appCode, string $operator = ''): array
    {
        $versions = Db::table('app_daily_show')->select('os_version')->where('app_code', $appCode)
            ->groupBy('os_version')->having('os_version', '<>', '')
            ->orderBy('os_version')->get()->pluck('os_version');
        switch ($operator) {
            case 'os_lt_8':
                return $versions->filter(function ($item) {
                    return version_compare($item, '8', '<');
                })->values()->toArray();
            case 'os_ge_8_lt_9':
                return $versions->filter(function ($item) {
                    return version_compare($item, '8', '>=') && version_compare($item, '9', '<');
                })->values()->toArray();
            case 'os_ge_9_lt_10':
                return $versions->filter(function ($item) {
                    return version_compare($item, '9', '>=') && version_compare($item, '10', '<');
                })->values()->toArray();
            case 'os_ge_10_lt_11':
                return $versions->filter(function ($item) {
                    return version_compare($item, '10', '>=') && version_compare($item, '11', '<');
                })->values()->toArray();
            case 'os_ge_11_lt_12':
                return $versions->filter(function ($item) {
                    return version_compare($item, '11', '>=') && version_compare($item, '12', '<');
                })->values()->toArray();
            case 'os_ge_12_lt_13':
                return $versions->filter(function ($item) {
                    return version_compare($item, '12', '>=') && version_compare($item, '13', '<');
                })->values()->toArray();
            case 'os_ge_13_lt_14':
                return $versions->filter(function ($item) {
                    return version_compare($item, '13', '>=') && version_compare($item, '14', '<');
                })->values()->toArray();
            case 'os_ge_14_lt_15':
                return $versions->filter(function ($item) {
                    return version_compare($item, '14', '>=') && version_compare($item, '15', '<');
                })->values()->toArray();
            case 'os_ge_15_lt_16':
                return $versions->filter(function ($item) {
                    return version_compare($item, '15', '>=') && version_compare($item, '16', '<');
                })->values()->toArray();
            case 'os_ge_16':
                return $versions->filter(function ($item) {
                    return version_compare($item, '16', '>=');
                })->values()->toArray();
            default:
                return $versions->values()->toArray();
        }
    }

    public function showList(string $appCode, string $type, array $params)
    {
        $appInfo = App::where('app_code', $appCode)->first();
        switch ($appInfo->type) {
            case AppConst::APP_TYPE_MIS:
                throw new BusinessException(ErrorCode::SERVER_ERROR, '网赚数据暂未支持');
                break;
            case AppConst::APP_TYPE_TOOLS:
                return $this->toolsShowList($appCode, $type, $params);
                break;
            default :
                throw new BusinessException(ErrorCode::SERVER_ERROR, '不存在的业务类型');
        }
    }

    public function toolsShowList(string $appCode, string $type, array $params)
    {
        switch ($type) {
            case 'installed':
            case 'active':
                return $this->installedOrShow($appCode, $type, $params);
            case 'deu':
                return $this->deu($appCode, $params);
            default:
                throw new BusinessException(ErrorCode::SERVER_ERROR, '不存在的业务类型');
        }
    }

    public function deu(string $appCode, array $params)
    {
        $installed = Db::table('app_middle_device')
            ->select([
                Db::raw('SUM(device_num) AS `device_num`'),
                'date',
                'type'
            ])->where('app_code', $appCode)
            ->when(($dateEnd = data_get($params, 'date_end')) && ($dateBegin = data_get($params, 'date_begin')), function ($query) use ($dateBegin, $dateEnd) {
                $query->where('date', '>=', $dateBegin)->where('date', '<=', $dateEnd);
            })
            ->whereIn('type', ['deu', 'dau', 'deu_old'])
            ->when($version = data_get($params, 'version'), function ($query) use ($version) {
                $query->where('version', $version);
            })
            ->when($channel = data_get($params, 'channel'), function ($query) use ($channel) {
                $query->where('channel', $channel);
            })
            ->when($osVersion = data_get($params, 'os_version'), function ($query) use ($appCode, $osVersion) {
                $query->whereIn('os_version', $this->getVersions($appCode, (string)$osVersion));
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
                        $query->where('vendor', 'huawei');
                        break;
                    case 'honor':
                        $query->where('vendor', 'honor');
                        break;
                    case 'oppo':
                        $query->where('vendor', 'oppo');
                        break;
                    case 'realme':
                        $query->where('vendor', 'realme');
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

        $show = Db::table('app_daily_show')->where('app_code', $appCode)->whereIn('type', ['deu', 'dau', 'deu_old'])
            ->when(($dateEnd = data_get($params, 'date_end')) && ($dateBegin = data_get($params, 'date_begin')), function ($query) use ($dateBegin, $dateEnd) {
                $query->where('date', '>=', $dateBegin)->where('date', '<=', $dateEnd);
            })
            ->when($version = data_get($params, 'version'), function ($query) use ($version) {
                $query->where('version', $version);
            })
            ->when($channel = data_get($params, 'channel'), function ($query) use ($channel) {
                $query->where('channel', $channel);
            })
            ->when($osVersion = data_get($params, 'os_version'), function ($query) use ($appCode, $osVersion) {
                $query->whereIn('os_version', $this->getVersions($appCode, (string)$osVersion));
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
                        $query->where('vendor', 'huawei');
                        break;
                    case 'honor':
                        $query->where('vendor', 'honor');
                        break;
                    case 'oppo':
                        $query->where('vendor', 'oppo');
                        break;
                    case 'realme':
                        $query->where('vendor', 'realme');
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
            })->select(['date', 'type', Db::raw("SUM(show_count) as show_count")])->groupBy('date', 'type')->get()->all();
        $result = [];
        $divFunc = function ($a, $b) {
            $c = $b > 0 ? $a / $b : 0;
            return round($c, 4);
        };
        for ($beginDate = strtotime(data_get($params, 'date_begin')); $beginDate <= strtotime(data_get($params, 'date_end'));) {
            $day = date('Y-m-d', $beginDate);
            $deu = collect($installed)->where('date', $day)->where('type', 'deu')->pluck('device_num')->first();
            $deuShow = collect($show)->where('date', $day)->where('type', 'deu')->pluck('show_count')->first();
            $deuOld = collect($installed)->where('date', $day)->where('type', 'deu_old')->pluck('device_num')->first();
            $deuOldShow = collect($show)->where('date', $day)->where('type', 'deu_old')->pluck('show_count')->first();
            $dau = collect($installed)->where('date', $day)->where('type', 'dau')->pluck('device_num')->first();
            $dauShow = collect($show)->where('date', $day)->where('type', 'dau')->pluck('show_count')->first();
            $result[] = [
                'date' => $day,
                'dau' => $dau ?? 0,
                'dau_avg' => $divFunc($dauShow, $dau),
                'deu' => $deu ?? 0,
                'deu_avg' => $divFunc($deuShow, $deu),
                'deu_old' => $deuOld ?? 0,
                'deu_old_avg' => $divFunc($deuOldShow, $deuOld),
                'rate' => ($divFunc($deu, $dau) * 100) . '%'
            ];
            $beginDate += 24 * 60 * 60;
        }
        return collect($result)->sortByDesc('date')->values()->toArray();
    }

    public function installedOrShow(string $appCode, string $type, array $params)
    {
        $data = Db::table('app_daily_show')->where('app_code', $appCode)->where('type', $type)
            ->when(($dateEnd = data_get($params, 'date_end')) && ($dateBegin = data_get($params, 'date_begin')), function ($query) use ($dateBegin, $dateEnd) {
                $query->where('date', '>=', $dateBegin)->where('date', '<=', $dateEnd);
            })
            ->when($version = data_get($params, 'version'), function ($query) use ($version) {
                $query->where('version', $version);
            })
            ->when($channel = data_get($params, 'channel'), function ($query) use ($channel) {
                $query->where('channel', $channel);
            })
            ->when($osVersion = data_get($params, 'os_version'), function ($query) use ($appCode, $osVersion) {
                $query->whereIn('os_version', $this->getVersions($appCode, (string)$osVersion));
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
                        $query->where('vendor', 'huawei');
                        break;
                    case 'honor':
                        $query->where('vendor', 'honor');
                        break;
                    case 'oppo':
                        $query->where('vendor', 'oppo');
                        break;
                    case 'realme':
                        $query->where('vendor', 'realme');
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
        $installed = Db::table('app_middle_device')->select('device_num', 'date')
            ->where('app_code', $appCode)
            ->when($version = data_get($params, 'version'), function ($query) use ($version) {
                $query->where('version', $version);
            })
            ->when($channel = data_get($params, 'channel'), function ($query) use ($channel) {
                $query->where('channel', $channel);
            })
            ->when($osVersion = data_get($params, 'os_version'), function ($query) use ($appCode, $osVersion) {
                $query->whereIn('os_version', $this->getVersions($appCode, (string)$osVersion));
            })
            ->where('type', $type)
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('platform', $platform);
            })
            ->when(in_array($launchType = data_get($params, 'launch_type'), ['0', '1']), function ($query) use ($launchType) {
                $query->where('launch_type', $launchType);
            })
            ->when($vendor = data_get($params, 'vendor'), function ($query) use ($vendor) {
                switch ($vendor) {
                    case 'huawei':
                        $query->where('vendor', 'huawei');
                        break;
                    case 'honor':
                        $query->where('vendor', 'honor');
                        break;
                    case 'oppo':
                        $query->where('vendor', 'oppo');
                        break;
                    case 'realme':
                        $query->where('vendor', 'realme');
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
            })->get()->all();
        $result = [];
        $divFunc = function ($a, $b) {
            $c = $b > 0 ? $a / $b : 0;
            return round($c, 4);
        };
        for ($beginDate = strtotime(data_get($params, 'date_begin')); $beginDate <= strtotime(data_get($params, 'date_end'));) {
            $day = date('Y-m-d', $beginDate);
            $dataFilterDay = collect($data)->where('date', $day)->all();
            $installedCount = collect($installed)->where('date', $day)->sum('device_num');
            $result[] = [
                'date' => $day,
                'installed_count' => $installedCount,
                'full_rate' => ($divFunc(collect($dataFilterDay)->sum('full_count'), collect($dataFilterDay)->sum('request_count')) * 100) . "%",
                'show_rate' => ($divFunc(collect($dataFilterDay)->sum('show_count'), collect($dataFilterDay)->sum('full_count')) * 100) . "%",
                'show_avg' => $divFunc(collect($dataFilterDay)->sum('show_count'), $installedCount),
                'show_avg_1' => $divFunc(collect($dataFilterDay)->sum('show_count_1'), $installedCount),
                'show_avg_2' => $divFunc(collect($dataFilterDay)->sum('show_count_2'), $installedCount),
                'show_avg_3' => $divFunc(collect($dataFilterDay)->sum('show_count_3'), $installedCount),
                'show_avg_4' => $divFunc(collect($dataFilterDay)->sum('show_count_4'), $installedCount),
                'show_avg_5' => $divFunc(collect($dataFilterDay)->sum('show_count_5'), $installedCount),
                'show_avg_6' => $divFunc(collect($dataFilterDay)->sum('show_count_6'), $installedCount),
                'show_avg_7' => $divFunc(collect($dataFilterDay)->sum('show_count_7'), $installedCount),
                'show_avg_14' => $divFunc(collect($dataFilterDay)->sum('show_count_14'), $installedCount),
                'show_avg_30' => $divFunc(collect($dataFilterDay)->sum('show_count_30'), $installedCount),
            ];
            $beginDate += 24 * 60 * 60;
        }
        return collect($result)->sortByDesc('date')->values()->toArray();
    }

    public function scheduleShowToday()
    {
        $this->scheduleShow(date("Y-m-d"));
    }

    public function scheduleShowYesterday()
    {
        $this->scheduleShow(date("Y-m-d", strtotime("yesterday")));
    }

    public function scheduleShow(string $doDate)
    {
        $appCodes = App::whereIn('ad_status', [AppConst::APP_PUT_STATUS_SELF, AppConst::APP_PUT_STATUS_AGENCY])->where('type', AppConst::APP_TYPE_TOOLS)
            ->pluck('app_code')->toArray();
        if ($doDate == date("Y-m-d")) {
            $days = [0];
        } else {
            $days = [0, 1, 2, 3, 4, 5, 6, 7, 14, 30];
        }

        $daysFunc = function ($day) use ($appCodes, $doDate) : void {
            $closure = function () use ($appCodes, $doDate, $day) {
                $appCodesFunc = function ($appCode) use ($day, $doDate) : void {
                    $types = ['installed', 'active', 'deu', 'deu_old', 'dau'];
                    $typeFunc = function ($type) use ($appCode, $day, $doDate) : void {
                        if ($day == 0) {
                            $this->scheduleEvent($appCode, $doDate, $day, $type, 'request');
                            $this->scheduleEvent($appCode, $doDate, $day, $type, 'full');
                        }
                        $this->scheduleEvent($appCode, $doDate, $day, $type);
                    };
                    array_map_safe($typeFunc, $types);
                };
                array_map_safe($appCodesFunc, $appCodes);
            };
            if ($doDate == date("Y-m-d")) {
                $closure();
            } else {
                go($closure);
            }
        };
        array_map_safe($daysFunc, $days);
    }

    protected function scheduleEvent($appCode, $doDate, $day, $type = 'installed', $eventType = 'show')
    {
        $temp = strtotime($doDate) < strtotime(date('Y-m-d', strtotime('-1 days'))) ? '_temp' : '';
        switch ($eventType) {
            case 'show':
                $appEventTable = "app_{$appCode}_event_ad_show{$temp}";
                $column = 'show_count';
                if ($day != 0) {
                    $column .= "_{$day}";
                }
                break;
            case 'full':
                $appEventTable = "app_{$appCode}_event_ad_full{$temp}";
                $column = 'full_count';
                break;
            case 'request':
                $appEventTable = "app_{$appCode}_event_ad_request{$temp}";
                $column = 'request_count';
                break;
            default:
                throw new BusinessException(ErrorCode::SERVER_ERROR, '不存在的业务类型');
        }

        $countNum = Db::connection('tools')->table("{$appEventTable} as appevent")
            ->leftjoin("app_{$appCode} as app", "app.android_id", '=', "appevent.android_id")
            ->leftJoin("{$this->mktAdvertiserTable} as ma", 'app.account_id', '=', 'ma.advertiser_id')
            ->where("appevent.create_time", ">=", strtotime($doDate))
            ->where("appevent.create_time", "<", strtotime($doDate) + 24 * 60 * 60)
            ->when($type == 'installed', function ($query) use ($doDate, $day) {
                $query->where("app.create_time", ">=", strtotime($doDate) - 24 * 60 * 60 * $day)
                    ->where("app.create_time", "<", strtotime($doDate) + 24 * 60 * 60 - 24 * 60 * 60 * $day);
            })
            ->when($type == 'active', function ($query) use ($doDate, $day) {
                $query->where("app.active_time", ">=", strtotime($doDate) - 24 * 60 * 60 * $day)
                    ->where("app.active_time", "<", strtotime($doDate) + 24 * 60 * 60 - 24 * 60 * 60 * $day);
            })
            ->when($type == 'deu_old', function ($query) use ($doDate, $day) {
                $query->where("app.create_time", "<", strtotime($doDate) - 24 * 60 * 60 * $day);
            })
            ->select([
                Db::raw("SUM(appevent.times) AS {$column}"), "app.version as version", "app.channel as channel",
                "app.vendor as vendor", Db::raw("appevent.info->'$.adType' as ad_type"), 'app.os_version', 'ma.platform', 'ma.launch_type'
            ])->groupBy('app.vendor', 'app.version', 'app.channel', Db::raw("appevent.info->'$.adType'"), 'app.os_version', 'ma.platform', 'ma.launch_type')->get()->all();
        foreach ($countNum as $item) {
            if (data_get($item, 'ad_type')) {
                $adType = trim(data_get($item, 'ad_type'), '"');
            }
            $attr = [
                'app_code' => $appCode,
                'date' => date("Y-m-d", strtotime($doDate) - 24 * 60 * 60 * $day),
                'version' => data_get($item, 'version', ''),
                'channel' => data_get($item, 'channel', 0),
                'vendor' => data_get($item, 'vendor', ''),
                'ad_type' => $adType ?? '',
                'type' => $type,
                'os_version' => data_get($item, 'os_version', ''),
                'platform' => data_get($item, 'platform', ''),
                'launch_type' => data_get($item, 'launch_type', -1),
            ];
            Db::table('app_daily_show')->updateOrInsert($attr, [$column => data_get($item, $column)]);
        }
    }
}