<?php

declare(strict_types=1);

namespace App\Service\Launch;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use Hyperf\DbConnection\Db;

class LaunchService
{
    public function hourLaunchChart(array $params)
    {
        $xAxis = $this->hourLaunchChartXAxis(data_get($params, 'date_begin_a'), data_get($params, 'date_end_a'));
        $aLineData = $this->hourLaunchChartLineData(data_get($params, 'date_begin_a'), data_get($params, 'date_end_a'), $params);
        $bLineData = $this->hourLaunchChartLineData(data_get($params, 'date_begin_b'), data_get($params, 'date_end_b'), $params);

        return [
            'x_axis' => $xAxis,
            'a_line' => [
                'name' => data_get($params, 'date_begin_a') . '至' . data_get($params, 'date_end_a'),
                'data' => $aLineData
            ],
            'b_line' => [
                'name' => data_get($params, 'date_begin_b') . '至' . data_get($params, 'date_end_b'),
                'data' => $bLineData
            ]
        ];
    }

    protected function hourLaunchChartXAxis(string $beginDate, string $endDate)
    {
        $xAxis = [];
        $currentTime = strtotime($beginDate);
        for (; $currentTime <= strtotime($endDate) + 60 * 60 * 24 - 1;) {
            $xAxis[] = date('H:i', $currentTime) . '-' . date('H:i', $currentTime + 60 * 60 - 1);
            $currentTime += 60 * 60;
        }

        return $xAxis;
    }


    protected function hourLaunchChartLineData(string $beginDate, string $endDate, array $params)
    {
        $target = (string )data_get($params, 'target');
        switch ($target) {
            case 'cost':
            case 'active':
            case 'key_action':
                return $this->hourLaunchChartLineDataColumn($target, $beginDate, $endDate, $params);
            case 'active_cost':
                return $this->hourLaunchChartLineDataMixedColumn('cost', 'active', $beginDate, $endDate, $params);
            case 'key_action_cost':
                return $this->hourLaunchChartLineDataMixedColumn('cost', 'key_action', $beginDate, $endDate, $params);
            case 'key_action_rate':
                return $this->hourLaunchChartLineDataMixedColumn('key_action', 'active', $beginDate, $endDate, $params);
            case 'installed':
                return $this->hourLaunchChartLineDataInstalled($beginDate, $endDate, $params);
            case 'installed_roi':
                return $this->hourLaunchChartLineDataInstalledRoi($beginDate, $endDate, $params);
        }
        throw new BusinessException(ErrorCode::PARAMS_ERROR, '不支持的查询类型');
    }

    protected function hourLaunchChartLineDataColumn(string $column, string $beginDate, string $endDate, array $params)
    {
        $data = Db::table('report_daily_advertiser as rdadv')
            ->select(['rdadv.hour_info', 'rdadv.date'])
            ->leftJoin('mkt_advertiser as madv', 'madv.advertiser_id', '=', 'rdadv.advertiser_id')
            ->when(data_get($params, 'app_code'), function ($query, $appCode) {
                $query->whereIn('rdadv.app_code', $appCode);
            })
            ->when(!is_null($launchType = data_get($params, 'launch_type')), function ($query) use ($launchType) {
                $query->where('madv.launch_type', $launchType);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('rdadv.platform', $platform);
            })
            ->where('rdadv.date', '>=', $beginDate)
            ->where('rdadv.date', '<=', $endDate)
            ->orderBy('rdadv.date')->get()->all();
        $result = [];
        foreach ($data as $item) {
            $currentDate = data_get($item, 'date');
            $hourInfo = json_decode(data_get($item, 'hour_info', '[]'), true);
            foreach ($hourInfo as $hour => $hourItem) {
                $hourValue = 0;
                if (isset($result[$currentDate . $hour])) {
                    $hourValue = $result[$currentDate . $hour];
                }
                $hourValue += data_get($hourItem, $column, 0);
                $result[$currentDate . $hour] = $hourValue;
            }
        }
        $targetValue = [];
        $currentTime = strtotime($beginDate);
        for (; $currentTime <= strtotime($endDate) + 60 * 60 * 24 - 1;) {
            $targetValue[] = round($result[date('Y-m-dH', $currentTime)] ?? 0, 4);
            $currentTime += 60 * 60;
        }
        return $targetValue;
    }

    protected function hourLaunchChartLineDataMixedColumn(string $column1, string $column2, string $beginDate, string $endDate, array $params)
    {
        $data = Db::table('report_daily_advertiser as rdadv')
            ->select(['rdadv.hour_info', 'rdadv.date'])
            ->leftJoin('mkt_advertiser as madv', 'madv.advertiser_id', '=', 'rdadv.advertiser_id')
            ->when(data_get($params, 'app_code'), function ($query, $appCode) {
                $query->whereIn('rdadv.app_code', $appCode);
            })
            ->when(!is_null($launchType = data_get($params, 'launch_type')), function ($query) use ($launchType) {
                $query->where('madv.launch_type', $launchType);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('rdadv.platform', $platform);
            })
            ->where('rdadv.date', '>=', $beginDate)
            ->where('rdadv.date', '<=', $endDate)
            ->orderBy('rdadv.date')->get()->all();
        $result = [];
        foreach ($data as $item) {
            $currentDate = data_get($item, 'date');
            $hourInfo = json_decode(data_get($item, 'hour_info', '[]'), true);
            foreach ($hourInfo as $hour => $hourItem) {
                $hourValue = [
                    $column1 => 0,
                    $column2 => 0,
                ];
                if (isset($result[$currentDate . $hour])) {
                    $hourValue = $result[$currentDate . $hour];
                }
                $hourValue[$column1] += data_get($hourItem, $column1, 0);
                $hourValue[$column2] += data_get($hourItem, $column2, 0);
                $result[$currentDate . $hour] = $hourValue;
            }
        }
        $targetValue = [];
        $currentTime = strtotime($beginDate);
        for (; $currentTime <= strtotime($endDate) + 60 * 60 * 24 - 1;) {
            $item = $result[date('Y-m-dH', $currentTime)] ?? [];
            $targetValue[] = safe_div($item[$column1] ?? 0, $item[$column2] ?? 0);
            $currentTime += 60 * 60;
        }
        return $targetValue;
    }

    protected function hourLaunchChartLineDataCost(string $column, string $beginDate, string $endDate, array $params)
    {
        $data = Db::table('report_daily_advertiser as rdadv')
            ->select(['rdadv.hour_info', 'rdadv.date'])
            ->leftJoin('mkt_advertiser as madv', 'madv.advertiser_id', '=', 'rdadv.advertiser_id')
            ->when(data_get($params, 'app_code'), function ($query, $appCode) {
                $query->whereIn('rdadv.app_code', $appCode);
            })
            ->when(!is_null($launchType = data_get($params, 'launch_type')), function ($query) use ($launchType) {
                $query->where('madv.launch_type', $launchType);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('rdadv.platform', $platform);
            })
            ->where('rdadv.date', '>=', $beginDate)
            ->where('rdadv.date', '<=', $endDate)
            ->orderBy('rdadv.date')->get()->all();
        $result = [];
        foreach ($data as $item) {
            $currentDate = data_get($item, 'date');
            $hourInfo = json_decode(data_get($item, 'hour_info', '[]'), true);
            foreach ($hourInfo as $hour => $hourItem) {
                $hourValue = 0;
                if (isset($result[$currentDate . $hour])) {
                    $hourValue = $result[$currentDate . $hour];
                }
                $hourValue += data_get($hourItem, $column, 0);
                $result[$currentDate . $hour] = $hourValue;
            }
        }
        $targetValue = [];
        $currentTime = strtotime($beginDate);
        for (; $currentTime <= strtotime($endDate) + 60 * 60 * 24 - 1;) {
            $targetValue[] = round($result[date('Y-m-dH', $currentTime)] ?? 0, 4);
            $currentTime += 60 * 60;
        }
        return $targetValue;
    }

    protected function hourLaunchChartLineDataActiveCost(string $beginDate, string $endDate, array $params)
    {
        $data = Db::table('report_daily_advertiser as rdadv')
            ->select(['rdadv.hour_info', 'rdadv.date'])
            ->leftJoin('mkt_advertiser as madv', 'madv.advertiser_id', '=', 'rdadv.advertiser_id')
            ->when(data_get($params, 'app_code'), function ($query, $appCode) {
                $query->whereIn('rdadv.app_code', $appCode);
            })
            ->when(!is_null($launchType = data_get($params, 'launch_type')), function ($query) use ($launchType) {
                $query->where('madv.launch_type', $launchType);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('rdadv.platform', $platform);
            })
            ->where('rdadv.date', '>=', $beginDate)
            ->where('rdadv.date', '<=', $endDate)
            ->orderBy('rdadv.date')->get()->all();
        $result = [];
        foreach ($data as $item) {
            $currentDate = data_get($item, 'date');
            $hourInfo = json_decode(data_get($item, 'hour_info', '[]'), true);
            foreach ($hourInfo as $hour => $hourItem) {
                $hourValue = [
                    'cost' => 0,
                    'active' => 0,
                ];
                if (isset($result[$currentDate . $hour])) {
                    $hourValue = $result[$currentDate . $hour];
                }
                $hourValue['cost'] += data_get($hourItem, 'cost', 0);
                $hourValue['active'] += data_get($hourItem, 'active', 0);
                $result[$currentDate . $hour] = $hourValue;
            }
        }
        $targetValue = [];
        $currentTime = strtotime($beginDate);
        for (; $currentTime <= strtotime($endDate) + 60 * 60 * 24 - 1;) {
            $item = $result[date('Y-m-dH', $currentTime)] ?? [];
            $targetValue[] = safe_div($item['cost'] ?? 0, $item['active'] ?? 0);
            $currentTime += 60 * 60;
        }
        return $targetValue;
    }

    protected function hourLaunchChartLineDataActionCost(string $beginDate, string $endDate, array $params)
    {
        $data = Db::table('report_daily_advertiser as rdadv')
            ->select(['rdadv.hour_info', 'rdadv.date'])
            ->leftJoin('mkt_advertiser as madv', 'madv.advertiser_id', '=', 'rdadv.advertiser_id')
            ->when(data_get($params, 'app_code'), function ($query, $appCode) {
                $query->whereIn('rdadv.app_code', $appCode);
            })
            ->when(!is_null($launchType = data_get($params, 'launch_type')), function ($query) use ($launchType) {
                $query->where('madv.launch_type', $launchType);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('rdadv.platform', $platform);
            })
            ->where('rdadv.date', '>=', $beginDate)
            ->where('rdadv.date', '<=', $endDate)
            ->orderBy('rdadv.date')->get()->all();
        $result = [];
        foreach ($data as $item) {
            $currentDate = data_get($item, 'date');
            $hourInfo = json_decode(data_get($item, 'hour_info', '[]'), true);
            foreach ($hourInfo as $hour => $hourItem) {
                $hourValue = [
                    'cost' => 0,
                    'key_action' => 0,
                ];
                if (isset($result[$currentDate . $hour])) {
                    $hourValue = $result[$currentDate . $hour];
                }
                $hourValue['cost'] += data_get($hourItem, 'cost', 0);
                $hourValue['key_action'] += data_get($hourItem, 'key_action', 0);
                $result[$currentDate . $hour] = $hourValue;
            }
        }
        $targetValue = [];
        $currentTime = strtotime($beginDate);
        for (; $currentTime <= strtotime($endDate) + 60 * 60 * 24 - 1;) {
            $item = $result[date('Y-m-dH', $currentTime)] ?? [];
            $targetValue[] = safe_div($item['cost'] ?? 0, $item['key_action'] ?? 0);
            $currentTime += 60 * 60;
        }
        return $targetValue;
    }

    protected function hourLaunchChartLineDataInstalled(string $beginDate, string $endDate, array $params)
    {
        $data = Db::table('report_daily_advertiser as rdadv')
            ->select(['rdadve.hour_sum', 'rdadv.date'])
            ->leftJoin('report_daily_advertiser_ext as rdadve', 'rdadve.id', '=', 'rdadv.id')
            ->leftJoin('mkt_advertiser as madv', 'madv.advertiser_id', '=', 'rdadv.advertiser_id')
            ->when(data_get($params, 'app_code'), function ($query, $appCode) {
                $query->whereIn('rdadv.app_code', $appCode);
            })
            ->when(!is_null($launchType = data_get($params, 'launch_type')), function ($query) use ($launchType) {
                $query->where('madv.launch_type', $launchType);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('rdadv.platform', $platform);
            })
            ->where('rdadv.date', '>=', $beginDate)
            ->where('rdadv.date', '<=', $endDate)
            ->orderBy('rdadv.date')->get()->all();
        $result = [];
        foreach ($data as $item) {
            $currentDate = data_get($item, 'date');
            $hourInfo = json_decode(data_get($item, 'hour_sum', '[]'), true);
            foreach ($hourInfo as $hour => $hourItem) {
                $hourValue = 0;
                if (isset($result[$currentDate . $hour])) {
                    $hourValue = $result[$currentDate . $hour];
                }
                $hourValue += data_get($hourItem, 'install', 0);
                $result[$currentDate . $hour] = $hourValue;
            }
        }
        $targetValue = [];
        $currentTime = strtotime($beginDate);
        for (; $currentTime <= strtotime($endDate) + 60 * 60 * 24 - 1;) {
            $targetValue[] = $result[date('Y-m-dH', $currentTime)] ?? 0;
            $currentTime += 60 * 60;
        }
        return $targetValue;
    }

    protected function hourLaunchChartLineDataInstalledRoi(string $beginDate, string $endDate, array $params)
    {
        $data = Db::table('report_daily_advertiser as rdadv')
            ->select(['rdadv.hour_info', 'rdadv.date', 'rdadve.hour_sum'])
            ->leftJoin('report_daily_advertiser_ext as rdadve', 'rdadve.id', '=', 'rdadv.id')
            ->leftJoin('mkt_advertiser as madv', 'madv.advertiser_id', '=', 'rdadv.advertiser_id')
            ->when(data_get($params, 'app_code'), function ($query, $appCode) {
                $query->whereIn('rdadv.app_code', $appCode);
            })
            ->when(!is_null($launchType = data_get($params, 'launch_type')), function ($query) use ($launchType) {
                $query->where('madv.launch_type', $launchType);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('rdadv.platform', $platform);
            })
            ->where('rdadv.date', '>=', $beginDate)
            ->where('rdadv.date', '<=', $endDate)
            ->orderBy('rdadv.date')->get()->all();
        $costData = [];
        $revenueData = [];
        foreach ($data as $item) {
            $currentDate = data_get($item, 'date');
            $hourInfo = json_decode(data_get($item, 'hour_info', '[]'), true);
            // 统计消耗
            foreach ($hourInfo as $hour => $hourItem) {
                $costValue = 0;
                if (isset($costData[$currentDate . $hour])) {
                    $costValue = $costData[$currentDate . $hour];
                }
                $costValue += data_get($hourItem, 'cost', 0);
                $costData[$currentDate . $hour] = $costValue;
            }
            $hourSum = json_decode(data_get($item, 'hour_sum', '[]'), true);
            // 统计收益
            foreach ($hourSum as $hour => $hourItem) {
                $revenueValue = 0;
                if (isset($revenueData[$currentDate . $hour])) {
                    $revenueValue = $revenueData[$currentDate . $hour];
                }
                $revenueValue += data_get($hourItem, 'install_revenue', 0);
                $revenueData[$currentDate . $hour] = $revenueValue;
            }

        }
        $targetValue = [];
        $currentCost = 0;
        $currentRevenue = 0;
        $currentTime = strtotime($beginDate);
        // 排序 计算roi
        for (; $currentTime <= strtotime($endDate) + 60 * 60 * 24 - 1;) {
            if (date('H', $currentTime) == '00') {
                $currentCost = 0;
                $currentRevenue = 0;
            }
            $currentCost += $costData[date('Y-m-dH', $currentTime)] ?? 0;
            $currentRevenue += $revenueData[date('Y-m-dH', $currentTime)] ?? 0;
            $targetValue[] = safe_div($currentRevenue, $currentCost);
            $currentTime += 60 * 60;
        }
        return $targetValue;
    }
}