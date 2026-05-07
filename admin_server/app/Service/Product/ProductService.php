<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Constants\AppConst;
use App\Model\App;
use Hyperf\DbConnection\Db;

class ProductService
{
    public $hourStep = 6 * 60 * 60;
    public $ecpmStep = 50;
    public $ecpmMax = 5000;
    public $timesMax = 50;

    public function scheduleDeviceEcpmEventHour()
    {
        $hour = floor(date('H') / 6);
        $hour = $hour == 0 ? 4: $hour;
        $this->scheduleDeviceEcpmEvent($hour);
    }

    public function scheduleDeviceEcpmEvent($hour)
    {
        $currentDate = date("Y-m-d", strtotime("yesterday"));
        if ($hour == 4){
            $currentDate = date("Y-m-d", strtotime("yesterday") - 24 * 60 * 60);
        }
        $appCodes = App::whereIn('ad_status', [AppConst::APP_PUT_STATUS_SELF, AppConst::APP_PUT_STATUS_AGENCY])->where('type', AppConst::APP_TYPE_TOOLS)
            ->pluck('app_code')->toArray();
        $adTypeArr = ['all', 'full', 'reword_video', 'native', 'inter', 'splash'];
        $adTypeFunc = function ($adType) use ($hour, $appCodes, $currentDate) {
            go(function () use($appCodes, $adType, $hour, $currentDate){
                $appCodesFunc = function ($appCode) use($adType, $hour, $currentDate) {
                    $sql = $this->scheduleGetSql($appCode, $currentDate, $adType, $hour);
                    $data = Db::connection('tools')->select($sql);
                    $data = object_to_arr($data);
                    $data = collect($data)->chunk(1000)->all();
                    foreach ($data as $item) {
                        $item = object_to_arr($item);
                        Db::table('app_middle_device_ecpm')->insert($item);
                    }

                };
                array_map_safe($appCodesFunc, $appCodes);
            });
        };
        array_map_safe($adTypeFunc, $adTypeArr);
    }

    protected function scheduleGetSql($appCode, $currentDate, $adType, $hour)
    {
        $ecpmStepMax = ceil($this->ecpmMax / $this->ecpmStep);
        $hourSection = $hour * $this->hourStep;
        $beginTime = strtotime($currentDate);
        $endTime = strtotime($currentDate) + 24 * 60 * 60;
        $sql = "SELECT '{$currentDate}' AS date, '{$appCode}' AS app_code, '{$adType}' AS ad_type, '{$hour}' AS hour_step, ".
            "COUNT(*) AS device_num, IF(aa.timestep >= {$this->timesMax}, {$this->timesMax}, timestep) AS times, ecpm_step, vendor,source from (SELECT ".
            "SUM(IF(1=1 ";
        switch ($adType){
            case 'full':
                $sql .= " AND appshow.info ->> '$.adType' in ('inter_full', 'full_video')";
                break;
            case 'reword_video':
                $sql .= " AND appshow.info ->> '$.adType' in ('reword_video', 'reword')";
                break;
            case 'native':
            case 'inter':
            case 'splash':
                $sql .= " AND appshow.info ->> '$.adType' = '{$adType}'";
        }
        $sql .=", 1, 0)) AS timestep, appshow.android_id, appshow.vendor, app.source,".
            "IF(SUM( appshow.info -> '$.realEcpm' )  >= {$this->ecpmMax}, ".
            "{$ecpmStepMax}, floor( SUM( appshow.info -> '$.realEcpm' )  / {$this->ecpmStep} )) AS ecpm_step ".
            "FROM app_{$appCode}_event_ad_show AS appshow LEFT JOIN app_{$appCode} AS app ON app.android_id = appshow.android_id ".
            "WHERE appshow.create_time <= app.create_time + {$hourSection} AND app.create_time >= {$beginTime} AND ".
            "app.create_time < {$endTime} ";

        $sql .= "GROUP BY appshow.android_id) AS aa GROUP BY times, ecpm_step, vendor,source";

        return $sql;
    }

    public function deviceEcpmEventList(string $appCode, array $params)
    {
        $data = Db::table('app_middle_device_ecpm')
            ->select([
                'date', 'ecpm_step', 'times', Db::raw("sum(device_num) as device_num")
            ])
            ->where('app_code', $appCode)
            ->where('date', '>=', data_get($params, 'date_begin'))
            ->where('date', '<=', data_get($params, 'date_end'))
            ->where('hour_step', data_get($params, 'hour_step'))
            ->when(data_get($params, 'vendor'), function ($query, $vendor) {
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
            ->where(function ($query) use ($params) {
                $adType = data_get($params, 'ad_type');
                if ($adType) {
                    $query->where('ad_type', $adType);
                } else {
                    $query->where('ad_type', 'all');
                }
            })
            ->when(data_get($params, 'source'), function ($query, $source) {
                $query->where('source', $source);
            })
            ->groupBy('date', 'ecpm_step', 'times')->get()->all();
        $installData = Db::connection('tools')->table("app_{$appCode} as app")
            ->select([Db::raw('count(*) AS device_num'), Db::raw('FROM_UNIXTIME(app.create_time, "%Y-%m-%d") as date')])
            ->where('create_time', '>=', strtotime(data_get($params, 'date_begin')))
            ->where('create_time', '<', strtotime(data_get($params, 'date_end')) + 24 * 60 * 60)
            ->when(data_get($params, 'vendor'), function ($query, $vendor) {
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
            ->when(data_get($params, 'source'), function ($query, $source) {
                $query->where('source', $source);
            })
            ->groupBy('date')->get()->all();
        $installData = collect($installData)->keyBy('date')->all();
        $result = [];
        $condition = $this->deviceEcpmEventCondition();
        foreach ($data as $item) {
            $currentData = $result[data_get($item, 'date') . data_get($item, 'ecpm_step')] ?? [];
            $times = data_get($item, 'times');
            $currentData["date"] = data_get($item, 'date');
            $currentData["device_num_temp_{$times}"] = data_get($item, 'device_num');
            $currentData["ecpm_section"] = $condition['ecpm'][data_get($item, 'ecpm_step') + 1];
            $currentData["ecpm_step"] = data_get($item, 'ecpm_step');
            $result[data_get($item, 'date') . data_get($item, 'ecpm_step')] = $currentData;
        }
        $result = collect($result)->map(function ($item){
            // 行累加
            for($i = 1; $i <= $this->timesMax; $i++){
                $num = 0;
                for($j = $i; $j <= $this->timesMax; $j++){
                    $num += data_get($item, "device_num_temp_{$j}");
                }
                data_set($item, "device_num_row_{$i}", $num);
            }
            return $item;
        })->keyBy(function ($item) {
            return data_get($item, 'date') . data_get($item, 'ecpm_step');
        })->toArray();
        $result = collect($result)->map(function ($item) use($result){
            // 列累加
            $date = data_get($item, 'date');
            $ecpmStep = data_get($item, 'ecpm_step');
            for($i = 1; $i <= $this->timesMax; $i++){
                $num = 0;
                for ($j = $ecpmStep; $j <= $this->ecpmMax / $this->ecpmStep; $j++) {
                    $num += data_get($result, $date . $j . ".device_num_row_{$i}");
                }
                data_set($item, "device_num_column_{$i}", $num);
            }
            return $item;
        })->all();
        $result = collect($result)->map(function ($item) use ($installData) {
            // 计算占比
            $deviceNum = data_get($installData, data_get($item, 'date').".device_num") ?: 0;
            data_set($item, 'device_total', $deviceNum);
            for($i = 1; $i <= $this->timesMax; $i++){
                $columnNum = data_get($item, "device_num_column_{$i}");
                $ratio = safe_div($columnNum, $deviceNum) * 100 . '%';
                data_set($item, "device_num_ratio_{$i}", $ratio);
                unset($item["device_num_row_{$i}"]);
                unset($item["device_num_column_{$i}"]);
                unset($item["device_num_temp_{$i}"]);
            }
            // 缩短日期
            data_set($item, 'date', date('y.m.d', strtotime(data_get($item, 'date'))));
            return $item;
        })->all();
        $result = collect($result)->sortBy([['date', 'desc'], ['ecpm_step', 'asc']])->values()->toArray();
        return $result;
    }

    public function deviceEcpmEventCondition()
    {
        $ecpmCondition = [];
        $ecpmStepMax = ceil($this->ecpmMax / $this->ecpmStep);
        for ($i = 0; $i <= $ecpmStepMax; $i++){
            $sectionFirst = $i * $this->ecpmStep / 1000;
            $ecpmCondition[$i + 1] = "{$sectionFirst}+";
        }
        $hourCondition = [];
        $hourStep = $this->hourStep / (60 * 60);
        for ($i = 1; $i <= 24 / $hourStep; $i++) {
            $hourCondition[$i] = "新增 " . $hourStep * $i . " 小时";
        }
        return [
            'hour' => $hourCondition,
            'ecpm' => $ecpmCondition,
            'times_max' => $this->timesMax
        ];
    }
}