<?php
declare(strict_types=1);
/**
 * 网赚广告打点数据
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Service;


use App\Controller\AbstractAction;
use App\Library\Aliyun\Sls;
use App\ModelMis\AppActiveKeep;
use App\Utils\Log;
use App\Utils\ValidateTrait;

class MisAdEventService extends AbstractAction
{
    use ValidateTrait;

    public $app_code;//应用app_code
    public $date_start;//开始日期（Y-m-d）
    public $date_end;//结束日期（Y-m-d）
    public $type;//数据类型 install-新增安装，activate-新增激活，deu_old-DEU老，dau，deu
    public $app_version;//应用版本
    public $channel;//渠道ID
    public $vendor;//厂商 huawei-华为，xiaomi-小米，oppo-OPPO，vivo-VIVO，other-其他
    public $ad_type;//广告类型 native-信息流，inter-插屏，inter_full-全屏插屏，full_video-全屏视频，splash-开屏，reword_video-激励视屏


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
            'ad_type' => ['nullable', 'in:native,inter,inter_full,full_video,splash,reword_video'],//广告类型
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
    public function setParams(array $params): MisAdEventService
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
        return [
            'user_num_data' => $this->getTypeNum(),
            'ad_event_data' => $this->getAdEventNum()
        ];
    }


    public function getTypeNum(): array
    {
        $start_int = $this->dateStr2Int($this->date_start);
        $end_int = $this->dateStr2Int($this->date_end);
        $field_map = [
            'install' => ['field_day' => 'install_day', 'where' => [['install_day', '>=', $start_int], ['install_day', '<=', $end_int], ['install_keep_num', '=', 0]]],
            'activate' => ['field_day' => 'activate_day', 'where' => [['activate_day', '>=', $start_int], ['activate_day', '<=', $end_int], ['activate_keep_num', '=', 0]]],
            'dau' => ['field_day' => 'active_day', 'where' => [['active_day', '>=', $start_int], ['active_day', '<=', $end_int]]],
            'deu' => ['field_day' => 'active_day', 'where' => [['active_day', '>=', $start_int], ['active_day', '<=', $end_int], ['is_ad_report', '=', 1]]],
            'deu_old' => ['field_day' => 'active_day', 'where' => [['active_day', '>=', $start_int], ['active_day', '<=', $end_int], ['is_ad_report', '=', 1], ['install_keep_num', '>', 0]]],
        ];
        $field = $field_map[$this->type];
        $field_day = $field['field_day'];
        $where = $field['where'];
        $query = (new AppActiveKeep())->setSuffix($this->app_code)->newQuery();
        $select = $field_day . ' as day, count(*) as user_num';
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
        return $query->get()->map(function ($row) {
            $row['day'] = $this->dateInt2Str($row['day']);
            return $row;
        })->toArray();
    }


    public function getAdEventNum()
    {
        $start = strtotime($this->date_start);
        $end = strtotime($this->date_end . ' 23:59:59') + 1;

        $field_map = [
            'install' => ['field_day' => 'install_time', 'where' => 'where date_format(install_time, \'%Y-%m-%d\') = date_format(__time__, \'%Y-%m-%d\')'],
            'activate' => ['field_day' => 'activate_time', 'where' => 'where date_format(activate_time, \'%Y-%m-%d\') = date_format(__time__, \'%Y-%m-%d\')'],
            'dau' => ['field_day' => '__time__', 'where' => ''],
            'deu' => ['field_day' => '__time__', 'where' => ''],
            'deu_old' => ['field_day' => '__time__', 'where' => 'where date_format(install_time, \'%Y%m%d\') < date_format(__time__, \'%Y%m%d\')'],
        ];

        $field = $field_map[$this->type];
        $field_day = $field['field_day'];
        $where = $field['where'];

        $query_filter = ' and not scene = in_sdk_draw';//过滤视频sdk自带广告，因为此类广告没有请求和填充只有展示，影响展示率的计算
        if (isset($this->app_version)) {
            $query_filter .= ' and  version : ' . $this->app_version;
        }
        if (isset($this->channel)) {
            $query_filter .= ' and  channel : ' . $this->channel;
        }
        if (isset($this->ad_type)) {
            $query_filter .= ' and  ad_type : ' . $this->ad_type;
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

        $query = '* and __topic__:' . $this->app_code . ' ' . $query_filter . '| select
          date_format(' . $field_day . ', \'%Y-%m-%d\') as "day",
          count_if(event_type = \'ad_request\') as "total_request_num",
          count_if(event_type = \'ad_full\') as "total_full_num",
          count_if(event_type = \'ad_show\') as "total_show_num",
          count_if(event_type = \'ad_request\' and region = \'in\' ) as "in_request_num",
          count_if(event_type = \'ad_full\' and region = \'in\' ) as "in_full_num",
          count_if(event_type = \'ad_show\' and region = \'in\' ) as "in_show_num",
          count_if(event_type = \'ad_request\' and region = \'out\' ) as "out_request_num",
          count_if(event_type = \'ad_full\' and region = \'out\' ) as "out_full_num",
          count_if(event_type = \'ad_show\' and region = \'out\' ) as "out_show_num"
        FROM log 
        ' . $where . '
        group by
          "day"
        order by
          "day" asc
        limit 10000';

        echo $query . PHP_EOL;
        $_list = $this->getTotalBySls($query, $start, $end);
        $list = [];
        foreach ($_list as $row) {
            $list[] = $row['contents'];
        }
        return $list;
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
            $Sls->setLogstore($Sls->AliyunConf['sls_logstore_ad_event']);
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
            'user_num' => 0,//类型用户数量
            'total_request_num' => 0,//总请求
            'total_full_num' => 0,//总填充
            'total_show_num' => 0,//总展示
            'in_request_num' => 0,//体内请求
            'in_full_num' => 0,//体内填充
            'in_show_num' => 0,//体内展示
            'out_request_num' => 0,//体外请求
            'out_full_num' => 0,//体外填充
            'out_show_num' => 0,//体外展示

        ];
        $start_time = strtotime($this->date_start);
        $end_time = strtotime($this->date_end);
        $total = ($end_time - $start_time) / 86400 + 1;
        $data_list = [];
        for ($i = ($total - 1); $i >= 0; $i--) {
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