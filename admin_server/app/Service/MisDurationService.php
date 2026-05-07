<?php
declare(strict_types=1);
/**
 * 网赚时长打点数据
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Service;


use App\Constants\ErrorCode;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Library\Aliyun\Sls;
use App\ModelMis\AppActiveKeep;
use App\Utils\Log;
use App\Utils\ValidateTrait;

class MisDurationService extends AbstractAction
{
    use ValidateTrait;

    public $app_code;//应用app_code
    public $date_start;//开始日期（Y-m-d）
    public $date_end;//结束日期（Y-m-d）
    public $type;//数据类型 install-新增安装，dau
    public $app_version;//应用版本
    public $channel;//渠道ID
    public $vendor;//厂商 huawei-华为，xiaomi-小米，oppo-OPPO，vivo-VIVO，other-其他

    public function rules(): array
    {
        return [
            'app_code' => ['required'],//app_code
            'date_start' => ['required', 'date'],//开始日期
            'date_end' => ['required', 'date'],//结束日期
            'type' => ['required', 'in:1,2,3,4,5'],//数据类型
            'app_version' => ['nullable'],//应用版本
            'channel' => ['nullable'],//渠道
            'vendor' => ['nullable', 'in:huawei,xiaomi,oppo,vivo,other'],//厂商
        ];
    }

    public function attributes(): array
    {
        return [
            'app_code' => '应用名称',
            'date_start' => '开始日期',
            'date_end' => '结束日期',
            'type' => '数据类型',
        ];
    }

    /**
     * 设置并验证参数
     * @param array $params
     * @return $this
     */
    public function setParams(array $params): MisDurationService
    {
        $type_map = [
            '1' => 'install',
            '2' => 'deu',
            '3' => 'activate',
            '4' => 'deu_old',
            '5' => 'dau',
        ];
        $params = $this->validated($params);
        foreach ($params as $key => $val) {
            if ($key == 'type') {
                $val = $type_map[$val];
            }
            $this->{$key} = $val;
        }
        return $this;
    }

    public function handle()
    {
        echo 'app_code: ' . $this->app_code . PHP_EOL;
        echo 'date_start: ' . $this->date_start . PHP_EOL;
        echo 'date_end: ' . $this->date_end . PHP_EOL;
        echo 'type: ' . $this->type . PHP_EOL;
        echo 'app_version: ' . $this->app_version . PHP_EOL;
        echo 'channel: ' . $this->channel . PHP_EOL;
        echo 'vendor: ' . $this->vendor . PHP_EOL;

        $list = $this->setDefData();
        if (count($list) > 30) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '最多查询30天的数据');
        }

        $type_num_list = $this->getTypeNum();
        $duration_num_list = $this->getDurationNum();

        foreach ($list as &$list_data) {
            $day = $list_data['day'];
            $duration_list = $duration_num_list[$day] ?? [];
            foreach ($duration_list as $row) {
                $vendor = $row['vendor'];
                $os_version = (int)$row['os_version'];
                $user_num = (int)$row['user_num'];
//                $total_time = round((int)$row['total_time'] / 60, 2);
                $total_time = (int)$row['total_time'];

                switch ($vendor) {
                    case 'huawei':
                    case 'honor':
                        $list_data['huawei_num'] += $user_num;
                        if ($os_version < 8) {
                            $list_data['huawei_lt8_time'] += $total_time;
                        }
                        if ($os_version >= 8 && $os_version <= 10) {
                            $list_data['huawei_ge8_le10_time'] += $total_time;
                        }
                        if ($os_version >= 11) {
                            $list_data['huawei_ge11_time'] += $total_time;
                        }
                        break;
                    case 'xiaomi':
                    case 'redmi':
                        $list_data['xiaomi_num'] += $user_num;
                        if ($os_version < 8) {
                            $list_data['xiaomi_lt8_time'] += $total_time;
                        }
                        if ($os_version >= 8 && $os_version <= 10) {
                            $list_data['xiaomi_ge8_le10_time'] += $total_time;
                        }
                        if ($os_version >= 11) {
                            $list_data['xiaomi_ge11_time'] += $total_time;
                        }
                        break;
                    case 'oppo':
                    case 'realme':
                        $list_data['oppo_num'] += $user_num;
                        if ($os_version < 8) {
                            $list_data['oppo_lt8_time'] += $total_time;
                        }
                        if ($os_version >= 8 && $os_version <= 10) {
                            $list_data['oppo_ge8_le10_time'] += $total_time;
                        }
                        if ($os_version >= 11) {
                            $list_data['oppo_ge11_time'] += $total_time;
                        }
                        break;
                    case 'vivo':
                        $list_data['vivo_num'] += $user_num;
                        if ($os_version < 8) {
                            $list_data['vivo_lt8_time'] += $total_time;
                        }
                        if ($os_version >= 8 && $os_version <= 10) {
                            $list_data['vivo_ge8_le10_time'] += $total_time;
                        }
                        if ($os_version >= 11) {
                            $list_data['vivo_ge11_time'] += $total_time;
                        }
                        break;
                    default:
                        $list_data['other_num'] += $user_num;
                        if ($os_version < 8) {
                            $list_data['other_lt8_time'] += $total_time;
                        }
                        if ($os_version >= 8 && $os_version <= 10) {
                            $list_data['other_ge8_le10_time'] += $total_time;
                        }
                        if ($os_version >= 11) {
                            $list_data['other_ge11_time'] += $total_time;
                        }
                }
            }


            $list_data['huawei_lt8_time'] = round($list_data['huawei_lt8_time'] / 60, 2);
            $list_data['huawei_ge8_le10_time'] = round($list_data['huawei_ge8_le10_time'] / 60, 2);
            $list_data['huawei_ge11_time'] = round($list_data['huawei_ge11_time'] / 60, 2);

            $list_data['xiaomi_lt8_time'] = round($list_data['xiaomi_lt8_time'] / 60, 2);
            $list_data['xiaomi_ge8_le10_time'] = round($list_data['xiaomi_ge8_le10_time'] / 60, 2);
            $list_data['xiaomi_ge11_time'] = round($list_data['xiaomi_ge11_time'] / 60, 2);

            $list_data['oppo_lt8_time'] = round($list_data['oppo_lt8_time'] / 60, 2);
            $list_data['oppo_ge8_le10_time'] = round($list_data['oppo_ge8_le10_time'] / 60, 2);
            $list_data['oppo_ge11_time'] = round($list_data['oppo_ge11_time'] / 60, 2);

            $list_data['vivo_lt8_time'] = round($list_data['vivo_lt8_time'] / 60, 2);
            $list_data['vivo_ge8_le10_time'] = round($list_data['vivo_ge8_le10_time'] / 60, 2);
            $list_data['vivo_ge11_time'] = round($list_data['vivo_ge11_time'] / 60, 2);

            $list_data['other_lt8_time'] = round($list_data['other_lt8_time'] / 60, 2);
            $list_data['other_ge8_le10_time'] = round($list_data['other_ge8_le10_time'] / 60, 2);
            $list_data['other_ge11_time'] = round($list_data['other_ge11_time'] / 60, 2);

            foreach ($type_num_list as $type_num) {
                $type_num['day'] = $this->dateInt2Str($type_num['day']);
                if ($day == $type_num['day']) {
                    $list_data = array_merge($list_data, $type_num);
                }
            }
        }


        $result = [];
        foreach ($list as $key => $val) {
            $result[$key]['day_time'] = $val['day'];
            $result[$key]['allNum'] = $allNum = $val['type_num'];
            $result[$key]['huawei'] = $val['huawei_num'];
            $result[$key]['huawei7'] = $val['huawei_num'] <= 0 ? 0 : round($val['huawei_lt8_time'] / $val['huawei_num'], 2);
            $result[$key]['huawei8'] = $val['huawei_num'] <= 0 ? 0 : round($val['huawei_ge8_lt10_time'] / $val['huawei_num'], 2);
            $result[$key]['huawei9'] = $val['huawei_num'] <= 0 ? 0 : round($val['huawei_ge11_time'] / $val['huawei_num'], 2);
            $result[$key]['huaweiProportion'] = $allNum <= 0 ? 0 : round($result['huawei_num'] / $allNum * 100, 2);

            $result[$key]['xiaomi'] = $val['xiaomi_num'];
            $result[$key]['xiaomi7'] = $val['xiaomi_num'] <= 0 ? 0 : round($val['xiaomi_lt8_time'] / $val['xiaomi_num'], 2);
            $result[$key]['xiaomi8'] = $val['xiaomi_num'] <= 0 ? 0 : round($val['xiaomi_ge8_lt10_time'] / $val['xiaomi_num'], 2);
            $result[$key]['xiaomi9'] = $val['xiaomi_num'] <= 0 ? 0 : round($val['xiaomi_ge11_time'] / $val['xiaomi_num'], 2);
            $result[$key]['xiaomiProportion'] = $allNum <= 0 ? 0 : round($result['xiaomi_num'] / $allNum * 100, 2);

            $result[$key]['oppo'] = $val['oppo_num'];
            $result[$key]['oppo7'] = $val['oppo_num'] <= 0 ? 0 : round($val['oppo_lt8_time'] / $val['oppo_num'], 2);
            $result[$key]['oppo8'] = $val['oppo_num'] <= 0 ? 0 : round($val['oppo_ge8_lt10_time'] / $val['oppo_num'], 2);
            $result[$key]['oppo9'] = $val['oppo_num'] <= 0 ? 0 : round($val['oppo_ge11_time'] / $val['oppo_num'], 2);
            $result[$key]['oppoProportion'] = $allNum <= 0 ? 0 : round($result['oppo_num'] / $allNum * 100, 2);

            $result[$key]['vivo'] = $val['vivo_num'];
            $result[$key]['vivo7'] = $val['vivo_num'] <= 0 ? 0 : round($val['vivo_lt8_time'] / $val['vivo_num'], 2);
            $result[$key]['vivo8'] = $val['vivo_num'] <= 0 ? 0 : round($val['vivo_ge8_lt10_time'] / $val['vivo_num'], 2);
            $result[$key]['vivo9'] = $val['vivo_num'] <= 0 ? 0 : round($val['vivo_ge11_time'] / $val['vivo_num'], 2);
            $result[$key]['vivoProportion'] = $allNum <= 0 ? 0 : round($result['vivo_num'] / $allNum * 100, 2);

            $result[$key]['other'] = $val['other_num'];
            $result[$key]['other7'] = $val['other_num'] <= 0 ? 0 : round($val['other_lt8_time'] / $val['other_num'], 2);
            $result[$key]['other8'] = $val['other_num'] <= 0 ? 0 : round($val['other_ge8_lt10_time'] / $val['other_num'], 2);
            $result[$key]['other9'] = $val['other_num'] <= 0 ? 0 : round($val['other_ge11_time'] / $val['other_num'], 2);
            $result[$key]['otherProportion'] = $allNum <= 0 ? 0 : round($result['other_num'] / $allNum * 100, 2);
        }
        return array_values($result);
    }


    public function getTypeNum(): array
    {
        $start_int = $this->dateStr2Int($this->date_start);
        $end_int = $this->dateStr2Int($this->date_end);
        $field_map = [
            'install' => ['field_day' => 'install_day', 'where' => [['install_day', '>=', $start_int], ['install_day', '<=', $end_int], ['install_keep_num', '=', 0]]],
            //'deu' => ['field_day' => 'active_day', 'where' => [['active_day', '>=', $start_int], ['active_day', '<=', $end_int], ['is_ad_report', '=', 1]]],
            'dau' => ['field_day' => 'active_day', 'where' => [['active_day', '>=', $start_int], ['active_day', '<=', $end_int]]],
        ];
        $field = $field_map[$this->type];
        $field_day = $field['field_day'];
        $where = $field['where'];
        $query = (new AppActiveKeep())->setSuffix($this->app_code)->newQuery();
        $select = $field_day . ' as day, count(*) as type_num';
        $query->selectRaw($select);
        $query->where($where);
        if (isset($this->app_version)) {
            $query->where('app_version', $this->app_version);
        }
        if (isset($this->channel)) {
            $query->where('channel_id', $this->channel);
        }
        if (isset($this->vendor)) {
            switch ($this->vendor) {
                case 'huawei':
                    $query->whereIn('vendor', ['huawei', 'honor']);
                    break;
                case 'xiaomi':
                    $query->whereIn('vendor', ['xiaomi', 'redmi']);
                    break;
                case 'oppo':
                    $query->whereIn('vendor', ['oppo', 'realme']);
                    break;
                case 'vivo':
                    $query->where('vendor', 'vivo');
                    break;
                case 'other':
                    $query->whereNotIn('vendor', ['huawei', 'honor', 'xiaomi', 'redmi', 'oppo', 'realme', 'vivo']);
                    break;
            }
        }
        $query->groupBy([$field_day]);
        $query->orderBy($field_day, 'asc');
        return $query->get()->toArray();
    }


    public function getDurationNum()
    {
        $start = strtotime($this->date_start);
        $end = strtotime($this->date_end . ' 23:59:59') + 1;

        $field_map = [
            'install' => ['field_day' => 'install_time', 'where' => 'and date_format(install_time, \'%Y-%m-%d\') = date_format(__time__, \'%Y-%m-%d\')'],
            //'deu' => ['field_day' => '__time__', 'where' => ''],
            'dau' => ['field_day' => '__time__', 'where' => ''],
        ];

        $field = $field_map[$this->type];
        $field_day = $field['field_day'];
        $where = $field['where'];

        $query_filter = '';
        if (isset($this->app_version)) {
            $query_filter .= ' and  version : ' . $this->app_version;
        }
        if (isset($this->channel)) {
            $query_filter .= ' and  channel : ' . $this->channel;
        }

        if (isset($this->vendor)) {
            switch ($this->vendor) {
                case 'huawei':
                    $query_filter .= ' and ( vendor : huawei or  vendor : honor)';
                    break;
                case 'xiaomi':
                    $query_filter .= ' and ( vendor : xiaomi or  vendor : redmi)';
                    break;
                case 'oppo':
                    $query_filter .= ' and ( vendor : oppo or  vendor : realme)';
                    break;
                case 'vivo':
                    $query_filter .= ' and vendor : vivo';
                    break;
                case 'other':
                    $query_filter .= ' and ( not  vendor :  huawei  and not  vendor :  honor  and not  vendor :  redmi  and not  vendor :  oppo  and not  vendor :  vivo  and not  vendor :  realme  and not  vendor :  xiaomi )';
                    break;
            }
        }

        $query = '* and __topic__:' . $this->app_code . ' and time_type : total_time ' . $query_filter . ' | select
          date_format(' . $field_day . ', \'%Y-%m-%d\') as "day",
          vendor,
          os_version,
          count(DISTINCT user_id) as user_num,
          sum(time) as total_time
        FROM log where time < 1000000000
        ' . $where . '
        group by
          "day", vendor, os_version
        order by
          "day" asc
        limit 100000';
        echo $query . PHP_EOL;
        $query_list = $this->getTotalBySls($query, $start, $end);
        $query_list = collect($query_list)->pluck('contents')->groupBy('day');


        echo '------------------------' . PHP_EOL;
        echo json_encode($query_list) . PHP_EOL;

        return $query_list;
    }

    /**
     * 查询统计数量
     * @param string $query 查询条件
     * @param int $start_time
     * @param int $end_time
     * @return array|mixed
     */
    public function getTotalBySls(string $query, int $start_time, int $end_time)
    {
        $log_list = [];
        try {
            $Sls = make(Sls::class);
            $Sls->setLogstore($Sls->AliyunConf['sls_logstore_ad_duration']);
            $Sls->setTopic($this->app_code);
            $res = $Sls->getLogsWithSql($start_time, $end_time, $query);
            $log_list = $res['log_list'];
        } catch (\Exception $exception) {
            Log::system('error', 'sls查询异常', ['msg' => $exception->getMessage(), 'query' => $query]);
        }
        return $log_list;
    }

    public function setDefData(): array
    {
        $default_data = [
            'type_num' => 0,//类型数据数量
            'huawei_num' => 0,//华为用户数
            'huawei_lt8_time' => 0,//华为8以下时长
            'huawei_ge8_le10_time' => 0,//华为8-10时长
            'huawei_ge11_time' => 0,//华为11及以上时长

            'xiaomi_num' => 0,//小米用户数
            'xiaomi_lt8_time' => 0,//小米8以下时长
            'xiaomi_ge8_le10_time' => 0,//小米8-10时长
            'xiaomi_ge11_time' => 0,//小米11及以上时长

            'oppo_num' => 0,//OPPO用户数
            'oppo_lt8_time' => 0,//OPPO8以下时长
            'oppo_ge8_le10_time' => 0,//OPPO8-10时长
            'oppo_ge11_time' => 0,//OPPO11及以上时长

            'vivo_num' => 0,//VIVO用户数
            'vivo_lt8_time' => 0,//VIVO8以下时长
            'vivo_ge8_le10_time' => 0,//VIVO8-10时长
            'vivo_ge11_time' => 0,//VIVO11及以上时长

            'other_num' => 0,//其他用户数
            'other_lt8_time' => 0,//其他8以下时长
            'other_ge8_le10_time' => 0,//其他8-10时长
            'other_ge11_time' => 0,//其他11及以上时长

        ];
        $start_time = strtotime($this->date_start);
        $end_time = strtotime($this->date_end);
        $total = ($end_time - $start_time) / 86400 + 1;
        $data_list = [];
        for ($i = 0; $i < $total; $i++) {
            $default_data['day'] = date('Y-m-d', $start_time + ($i * 86400));
            $data_list[] = $default_data;
        }

        return $data_list;
    }

    public function dateInt2Str(int $day)
    {
        $time = strtotime((string)$day);
        return date('Y-m-d', $time);
    }

    public function dateStr2Int(string $day): int
    {
        $time = strtotime((string)$day);
        return (int)date('Ymd', $time);
    }
}
