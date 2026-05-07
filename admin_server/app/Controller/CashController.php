<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\AppConst;
use App\Elasticsearch\Elasticsearch;
use App\Model\App as AppUc;
use Hyperf\HttpServer\Response;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\DbConnection\Db;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Service\MisAdEventService;
use App\Service\MisDurationService;

class CashController extends AbstractController
{
    public function test()
    {
        $es = new Elasticsearch();
        $esIndex = 'middle';
        if (!$es->indexExistsEs($esIndex)) {
            $es->createIndex($esIndex);
        }
        $data = [
            'name' => '小红',
            'age' => 18,
        ];
        $parames = [
            'index' => 'middle',
            'id' => 1,
            'body' => $data,
        ];
        $result = $es->indexEs($parames);
        var_dump($result);
    }

    private $redis;

    /**
     * 获取广告变现
     */
    public function cashList(RequestInterface $request)
    {
        $type = $request->query('type', '1');
        $timeSlot = $request->query('startTime');
        if (empty($timeSlot)) {
            $startTime = strtotime(date('Y-m-d', time())) + 1;
            $endTime = strtotime(date('Y-m-d', time())) + 86400 - 1;
        } else {
            $startTime = strtotime(explode('T', $timeSlot[0])[0]) + 1;
            $endTime = strtotime(explode('T', $timeSlot[1])[0]) + 86400 - 1;
        }
        $appCode = $request->query('appcode', 'wifi');
        $channel = $request->query('channel', '');
        $vendor = $request->query('vendor', '');
        $version = $request->query('all_version', '');

        $where = $totalWhere = '';
        $App = AppUc::query()->where('app_code', $appCode)->first();
        //网赚
        if ($App->type == AppConst::APP_TYPE_MIS) {
            $params['app_code'] = $appCode;
            $params['date_start'] = date('Y-m-d', $startTime);
            $params['date_end'] = date('Y-m-d', $endTime);
            $params['app_version'] = $version;
            if (!empty($vendor)) {
                $params['vendor'] = strtolower($vendor);
            }
            $params['channel'] = $channel;
            $params['type'] = $type;
            $list = make(MisDurationService::class)->setParams($params)->handle();
            return $this->responseSuccess(['list' => $list]);
        } else {
            $redisKey = 'cashList-' . $type . $startTime . $endTime . $channel . $vendor;
            $container = ApplicationContext::getContainer();
            $this->redis = $container->get(\Redis::class);
            $list = $this->redis->get($redisKey);
            $list = '';
            if ($list) return $this->responseSuccess(json_decode($list, true));
            switch ($type) {
                case 1: //新增安装
                    $where = " 1 = 1";
                    if (!empty($channel)) {
                        $where .= " and channel = $channel";
                    }
                    if (!empty($vendor)) {
                        $where .= " and vendor = '$vendor'";
                    }
                    if (!empty($version)) {
                        $where .= " and version = '$version'";
                    }
                    $result = $this->installList($appCode, $startTime, $endTime, $where);
                    break;
                case 2: //deu
                    $totalWhere = ' where 1=1 ';
                    if (!empty($channel)) {
                        $where .= " and c.channel = $channel";
                        $totalWhere .= " and a.channel = $channel";
                    }
                    if (!empty($vendor)) {
                        $where .= " and c.vendor = '$vendor'";
                        $totalWhere .= " and a.vendor = '$vendor'";
                    }
                    if (!empty($version)) {
                        $where .= " and c.version = '$version'";
                        $totalWhere .= " and a.version = '$version'";
                    }
                    $result = $this->deuList($appCode, $startTime, $endTime, $where, $totalWhere);
                    break;
                case 5: //dau
                    $where = ' 1 = 1 ';
                    if (!empty($channel)) {
                        $where .= " and a.channel = $channel";
                    }
                    if (!empty($vendor)) {
                        $where .= " and a.vendor = '$vendor'";
                    }
                    if (!empty($version)) {
                        $where .= " and a.version = '$version'";
                    }
                    $result = $this->dauList($appCode, $startTime, $endTime, $where, $totalWhere);
                    break;
                case 'only': //独立设备
                    $page = $request->query('page', 1);
                    $limit = $request->query('limit', 20);
                    $export = $request->query('export', 0);
                    if (!empty($channel)) $totalWhere .= $where .= " and channel = $channel";
                    if (!empty($vendor)) $totalWhere .= $where .= " and vendor = $vendor";
                    $result = $this->onlyList($appCode, $startTime, $endTime, $where, $totalWhere, $page, $limit, $export);
                    break;
            }
            $this->redis->set($redisKey, json_encode($result), 1200);
            return $this->responseSuccess(['list' => $result]);
        }

    }

    //新增安装 心跳时长
    private function installList($appCode, $startTime, $endTime, $where)
    {
        $tablea = 'app_' . $appCode;
        $tableb = 'app_' . $appCode . '_active';
        $sql = " select 
                    install_date as day_time,
                    vendor as channel,
                    os_version as version, 
                    count(distinct(android_id)) as num,
                    sum(time) as 'allTime'
                from (
                   SELECT
                        a.android_id,
                        a.version,
                        a.channel,
                        case a.vendor when 'vivo' then 'vivo' when 'OPPO' then 'oppo'
                        when 'HUAWEI' then 'huawei' when 'HONOR' then 'huawei' 
                        when 'Xiaomi' then 'xiaomi' else 'other' end vendor,
                        case when a.os_version < 8 then '7' when a.os_version >= 8 and a.os_version < 11 then '8'
                        when a.os_version >= 11 then '9' end os_version,
                        FROM_UNIXTIME(a.create_time,'%Y-%m-%d') as install_date,
                        b.time AS time
                    FROM
                        {$tablea} a
                    LEFT JOIN (SELECT
                        android_id,
                        round( sum( time )/ 60000, 2 ) AS time,
                        FROM_UNIXTIME(create_time,'%Y-%m-%d') as active_date
                    FROM
                        {$tableb}
                    WHERE
                        create_time BETWEEN {$startTime} AND {$endTime}
                    GROUP BY
                        android_id,active_date
					HAVING
                        time <= 24 * 60) b ON a.android_id = b.android_id AND FROM_UNIXTIME(a.create_time,'%Y-%m-%d') = b.active_date 
                    WHERE
                        a.create_time BETWEEN {$startTime} AND {$endTime}
                    HAVING $where
                ) as aa
                group by day_time,vendor,os_version order by day_time desc";
        $data = Db::connection('tools')->select($sql);
        if (empty($data)) {
            return [];
        }
        $data = json_decode(json_encode($data), true);
        $result = [];
        foreach ($data as $key => $val) {
            $result[$val['day_time']][$val['channel'] . $val['version']] = round($val['allTime'] / $val['num'], 2);
            $result[$val['day_time']][$val['channel']] = !empty($result[$val['day_time']][$val['channel']]) ? $result[$val['day_time']][$val['channel']] + $val['num'] : $val['num'];
            $result[$val['day_time']]['day_time'] = $val['day_time'];
        }
        $vendorArr = ['huawei', 'xiaomi', 'oppo', 'vivo', 'other'];
        foreach ($result as $day => $value) {
            $result[$day]['allNum'] = 0;
            foreach ($vendorArr as $v) {
                $result[$day]['allNum'] += $value[$v] ?? 0;
            }
            foreach ($vendorArr as $v) {
                $result[$day][$v . 'Proportion'] = round(($value[$v] ?? 0) / $result[$day]['allNum'] * 100, 2) . '%';
            }
        }
        return array_values($result);
    }

    // dau 心跳时长
    private function dauList($appCode, $startTime, $endTime, $where, $totalWhere)
    {
        $table = "app_{$appCode}_active";
        $sql = " select 
                    day_time,
                    vendor as channel,
                    os_version as version, 
                    count(distinct(android_id)) as num,
                    sum(time) as 'allTime'
                from (
                   SELECT
                        android_id,
                        version,
                        channel,
                        case vendor when 'vivo' then 'vivo' when 'OPPO' then 'oppo'
                        when 'HUAWEI' then 'huawei' when 'HONOR' then 'huawei' 
                        when 'Xiaomi' then 'xiaomi' else 'other' end vendor,
                        case when os_version < 8 then '7' when os_version >= 8 and os_version < 11 then '8'
                        when os_version >= 11 then '9' end os_version,
                        round( sum( time )/ 60000, 2 ) AS time,
                        FROM_UNIXTIME(create_time,'%Y-%m-%d') as day_time
                    FROM
                        {$table}
                    WHERE
                        create_time BETWEEN {$startTime} AND {$endTime} 
                    GROUP BY
                        android_id ,day_time
                    HAVING
                        time <= 24 * 60
                ) as a
                where $where group by day_time,vendor,os_version order by day_time desc";
        $data = Db::connection('tools')->select($sql);
        if (empty($data)) return [];
        $data = json_decode(json_encode($data), true);
        $result = [];
        foreach ($data as $key => $val) {
            $result[$val['day_time']][$val['channel'] . $val['version']] = round($val['allTime'] / $val['num'], 2);
            $result[$val['day_time']][$val['channel']] = !empty($result[$val['day_time']][$val['channel']]) ? $result[$val['day_time']][$val['channel']] + $val['num'] : $val['num'];
            $result[$val['day_time']]['day_time'] = $val['day_time'];
        }
        $vendorArr = ['huawei', 'xiaomi', 'oppo', 'vivo', 'other'];
        foreach ($result as $day => $value) {
            $result[$day]['allNum'] = 0;
            foreach ($vendorArr as $v) {
                $result[$day]['allNum'] += $value[$v] ?? 0;
            }
            foreach ($vendorArr as $v) {
                $result[$day][$v . 'Proportion'] = round(($value[$v] ?? 0) / $result[$day]['allNum'] * 100, 2) . '%';
            }
        }

        return array_values($result);
    }

    // deu 心跳时长
    private function deuList($appCode, $startTime, $endTime, $where, $totalWhere)
    {
        $nowDay = strtotime(date('Y-m-d', time()));
        $tablea = 'app_' . $appCode;
        $tableb = 'app_' . $appCode . '_active';
        $tableShow = 'app_' . $appCode . '_event_ad_show';
        $tableShopTemp = 'app_' . $appCode . '_event_ad_show_temp';
        $union = "";
        if ($nowDay > $startTime) { //需要加之前的数据
            $union = " UNION ALL SELECT create_time,version,channel, vendor, 
            android_id, times FROM $tableShopTemp WHERE create_time between $startTime and $endTime group by android_id";
        }
        $sql = " select FROM_UNIXTIME(c.create_time,'%Y-%m-%d') as day_time,
                case c.vendor when 'vivo' then 'vivo' when 'OPPO' then 'oppo'
                when 'HUAWEI' then 'huawei' when 'Xiaomi' then 'xiaomi' else 'other' end channel,
                case when b.os_version < 8 then '7' when b.os_version >= 8 and b.os_version < 11 then '8'
                when b.os_version >= 11 then '9' end version, count(distinct(b.android_id)) as num,
                round(sum(b.time)/60000,2) as 'allTime'
                from (
                    SELECT create_time,vendor,channel,version,android_id,times 
                    FROM $tableShow WHERE create_time between $startTime and $endTime 
                    group by android_id $union
                ) as c 
                left join $tableb as b on c.android_id = b.android_id and 
                b.create_time <= UNIX_TIMESTAMP(FROM_UNIXTIME(c.create_time+86400,'%Y-%m-%d')) where 
                b.create_time between $startTime and $endTime $where group by day_time,channel,version order by day_time desc;";
        Db::connection('tools')->enableQueryLog();
        $data = Db::connection('tools')->select($sql);
        var_dump(Db::connection('tools')->getQueryLog());
        if (empty($data)) return '';
        $data = json_decode(json_encode($data), true);
        $totalSql = "select FROM_UNIXTIME(a.create_time,'%Y-%m-%d') as day_time,count(distinct(a.android_id)) as allNum
                    from ( SELECT create_time,channel,vendor,version,android_id,times FROM $tableShow 
                    WHERE create_time between $startTime and $endTime $union ) as a $totalWhere group by day_time";
        $total = Db::connection('tools')->select($totalSql);
        $total = json_decode(json_encode($total), true);
        $totalData = array_column($total, null, 'day_time');
        $result = [];
        foreach ($data as $key => $val) {
            $allNum = $totalData[$val['day_time']]['allNum'];
            $result[$val['day_time']][$val['channel'] . $val['version']] = round($val['allTime'] / $val['num'], 2);
            $result[$val['day_time']][$val['channel']] = !empty($result[$val['day_time']][$val['channel']]) ? $result[$val['day_time']][$val['channel']] + $val['num'] : $val['num'];
            $proportion = $allNum < 0 ? 0 : round($result[$val['day_time']][$val['channel']] / $allNum * 100, 2);
            $result[$val['day_time']][$val['channel'] . 'Proportion'] = $proportion . '%';
            $result[$val['day_time']]['allNum'] = $allNum;
            $result[$val['day_time']]['day_time'] = $val['day_time'];
        }
        return array_values($result);
    }

    // 独立设备 心跳时长
    private function onlyList($appCode, $startTime, $endTime, $where, $totalWhere, $page, $limit, $export)
    {
        $tablea = 'app_' . $appCode;
        $tableb = 'app_' . $appCode . '_active';
        $offset = ($page - 1) * $limit;
        $sql = "select FROM_UNIXTIME( a.create_time, '%Y-%m-%d %H:%i:%s' ) AS 'day_time',a.android_id,
                ch.name,a.vendor,a.os_version,a.version,a.model, ROUND(avg(d.time)/60000,2) avgTime,
                case when d.create_time <= (a.create_time+43200) then 1 
                when  d.create_time <= (a.create_time+86400) then 2 
                when  d.create_time <= (a.create_time+172800) then 3 end timeSlot
                from (select * from $tablea where create_time between $startTime and $endTime $where  limit $offset,$limit) as a 
                left join $tableb as d on a.android_id = d.android_id and d.create_time <= (a.create_time+172800)
                left join app_channel as ch on ch.id = a.channel group by a.android_id,timeSlot";
        $data = Db::connection('tools')->select($sql);
        $data = json_decode(json_encode($data), true);
        $resultData = [];
        foreach ($data as $key => $val) {
            $resultData[$val['android_id']]['day_time'] = $val['day_time'];
            $resultData[$val['android_id']]['android_id'] = $val['android_id'];
            $resultData[$val['android_id']]['name'] = $val['name'];
            $resultData[$val['android_id']]['vendor'] = $val['vendor'];
            $resultData[$val['android_id']]['os_version'] = $val['os_version'];
            $resultData[$val['android_id']]['version'] = $val['version'];
            $resultData[$val['android_id']]['model'] = $val['model'];
            $resultData[$val['android_id']]['avg_time' . $val['timeSlot']] = $val['avgTime'];
        }
        $totalSql = "select count(id) as total from $tablea where create_time between $startTime and $endTime $totalWhere";
        $totalData = Db::connection('tools')->select($totalSql);
        $totalData = json_decode(json_encode($totalData), true);
        return array('list' => array_values($resultData), 'total' => $totalData[0]['total'], 'page' => $page, 'limit' => $limit);
    }

    public function expload($fileName, $data, $title)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        //设置单元格内容
        foreach ($title as $key => $value) {
            $sheet->setCellValueByColumnAndRow($key + 1, 1, $value);
        }
        $row = 2; //从第二行开始
        foreach ($data as $k => $val) {
            $row = $row + $key;
            $sheet->setCellValue('A' . $row, $val['day_time']);
            $sheet->setCellValue('B' . $row, $val['android_id']);
            $sheet->setCellValue('C' . $row, $val['name']);
            $sheet->setCellValue('D' . $row, $val['vendor']);
            $sheet->setCellValue('E' . $row, $val['model']);
            $sheet->setCellValue('F' . $row, $val['os_version']);
            $sheet->setCellValue('G' . $row, !empty($val['avg_time1']) ? $val['avg_time1'] : 0);
            $sheet->setCellValue('H' . $row, !empty($val['avg_time2']) ? $val['avg_time2'] : 0);
            $sheet->setCellValue('I' . $row, !empty($val['avg_time3']) ? $val['avg_time3'] : 0);
        }

        //正常数据下面格式显示
//        foreach ($data as $item) {
//            $column = 1;
//            foreach ($item as $value) {
//                $sheet->setCellValueByColumnAndRow($column, $row, $value);
//                $column++;
//            }
//            $row++;
//        }
        return $this->saveToBrowserByTmp($fileName, $spreadsheet);
    }

    //保存临时文件在从浏览器自动下载到本地
    public function saveToBrowserByTmp($fileName, $spreadsheet)
    {
        $fileName = $fileName . '.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, "Xlsx");
        //保存到服务器的临时文件下
        $writer->save("./tmp.xlsx");
        //将文件转字符串
        $content = file_get_contents('./tmp.xlsx');
        //删除临时文件
        unlink("./tmp.xlsx");
        $response = new Response();
        //xls='application/vnd.ms-excel'
        //xlsx='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        return $response->withHeader('content-description', 'File Transfer')
            ->withHeader('content-type', $contentType)
            ->withHeader('content-disposition', "attachment; filename={$fileName}")
            ->withHeader('content-transfer-encoding', 'binary')
            ->withHeader('pragma', 'public')
            ->withBody(new SwooleStream((string)$content));
    }

    /**
     *  人均展现
     */
    public function averageShow(RequestInterface $request)
    {
        $timeSlot = $request->input('startTime');
        if (empty($timeSlot)) {
            $startTime = date('Y-m-d', time());
            $endTime = date('Y-m-d', time());
        } else {
            $startTime = $timeSlot[0];
            $endTime = $timeSlot[1];
        }
        $appCode = $request->input('appcode');
        $channel = $request->input('channel', '');
        $vendor = $request->input('vendor', '');
        $version = $request->input('all_version', '');
        $type = $request->input('type', '1');
        $adType = $request->input('adType', '');
        $App = AppUc::query()->where('app_code', $appCode)->first();
        //网赚
        if ($App->type == AppConst::APP_TYPE_MIS) {
            $params = [];
            $params['app_code'] = $appCode;
            $params['date_start'] = $startTime;
            $params['date_end'] = $endTime;
            $params['type'] = $type;
            $params['app_version'] = $version;
            $params['channel'] = $channel;
            $params['vendor'] = $vendor;
            $params['ad_type'] = $adType;

            $data = make(MisAdEventService::class)->setParams($params)->handle();
            $_userData = $data['user_num_data'];
            $userData = [];
            foreach ($_userData as $row) {
                $row = collect($row)->toArray();
                $userData[$row['day']] = $row;
            }

            $_adEventData = $data['ad_event_data'];
            $adEventData = [];
            foreach ($_adEventData as $row) {
                $row = collect($row)->toArray();
                $adEventData[$row['day']] = $row;
            }

            $data = $this->averageTools($userData, $adEventData, $startTime, $endTime);
        } else {

            /*用户数查询*/
            $userQuery = Db::connection('tools')->table('middle_event_user_new');
            $userQuery->select([
                'date as day',
                Db::raw('SUM(user_num) AS user_num')
            ]);
            $userQuery->where('app_code', $appCode);
            $userQuery->where('is_new', $type);
            $userQuery->whereBetween('date', [$startTime, $endTime]);

            if (!empty($channel)) {
                $userQuery->where('channel', $channel);
            }
            if (!empty($version)) {
                $userQuery->where('version', $version);
            }
            if (!empty($vendor)) {
                switch ($vendor) {
                    case 'huawei':
                        $userQuery->whereIn('vendor', ['huawei', 'honor']);
                        break;
                    case 'xiaomi':
                        $userQuery->whereIn('vendor', ['xiaomi', 'redmi']);
                        break;
                    case 'oppo':
                        $userQuery->whereIn('vendor', ['oppo', 'realme']);
                        break;
                    case 'vivo':
                        $userQuery->where('vendor', 'vivo');
                        break;
                    case 'other':
                        $userQuery->whereNotIn('vendor', ['huawei', 'honor', 'xiaomi', 'redmi', 'oppo', 'realme', 'vivo']);
                        break;
                }
            }
            $_userData = $userQuery->groupBy(['date'])->orderBy('date', 'desc')->get()->toArray();
            $userData = [];
            foreach ($_userData as $row) {
                $row = collect($row)->toArray();
                $userData[$row['day']] = $row;
            }

            /*广告打点数*/
            $adEventQuery = Db::connection('tools')->table('app_middle_event_ad_new');
            $adEventQuery->select([
                'date AS day',
                Db::raw('SUM(IF(`event`=\'request\',request_num,0)) AS total_request_num'),//总请求
                Db::raw('SUM(IF(`event`=\'full\',request_num,0)) AS total_full_num'),//总填充
                Db::raw('SUM(IF(`event`=\'show\',request_num,0)) AS total_show_num'),//总展示
                Db::raw('SUM( IF ( is_inside = 1 AND `event` = \'show\', request_num, 0 ) ) AS in_show_num'),
                Db::raw('SUM( IF ( is_inside = 2 AND `event` = \'show\', request_num, 0 ) ) AS out_show_num'),
                Db::raw('SUM( IF ( is_inside = 1 AND `event` = \'full\', request_num, 0 ) ) AS in_full_num'),
                Db::raw('SUM( IF ( is_inside = 2 AND `event` = \'full\', request_num, 0 ) ) AS out_full_num'),
                Db::raw('SUM( IF ( is_inside = 1 AND `event` = \'request\', request_num, 0 ) ) AS in_request_num'),
                Db::raw('SUM( IF ( is_inside = 2 AND `event` = \'request\', request_num, 0 ) ) AS out_request_num')
            ]);
            $adEventQuery->where('app_code', $appCode);
            $adEventQuery->where('is_new', $type);
            $adEventQuery->whereBetween('date', [$startTime, $endTime]);

            if (!empty($channel)) {
                $adEventQuery->where('channel', $channel);
            }
            if (!empty($version)) {
                $adEventQuery->where('version', $version);
            }
            if (!empty($adType)) {
                $adEventQuery->where('ad_type', $adType);
            }
            if (!empty($vendor)) {
                switch ($vendor) {
                    case 'huawei':
                        $adEventQuery->whereIn('vendor', ['huawei', 'honor']);
                        break;
                    case 'xiaomi':
                        $adEventQuery->whereIn('vendor', ['xiaomi', 'redmi']);
                        break;
                    case 'oppo':
                        $adEventQuery->whereIn('vendor', ['oppo', 'realme']);
                        break;
                    case 'vivo':
                        $adEventQuery->where('vendor', 'vivo');
                        break;
                    case 'other':
                        $adEventQuery->whereNotIn('vendor', ['huawei', 'honor', 'xiaomi', 'redmi', 'oppo', 'realme', 'vivo']);
                        break;
                }
            }
            $_adEventData = $adEventQuery->groupBy(['date'])->orderBy('date', 'desc')->get()->toArray();
            $adEventData = [];
            foreach ($_adEventData as $row) {
                $row = collect($row)->toArray();
                $adEventData[$row['day']] = $row;
            }

            $data = $this->averageTools($userData, $adEventData, $startTime, $endTime);

        }
        return $this->responseSuccess(['list' => $data]);
    }

    protected function averageTools($userData, $adEventData, $startTime, $endTime)
    {
        $default_data = [
            'user_num' => 0,//类型数据数量
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
        $start_time = strtotime($startTime);
        $end_time = strtotime($endTime);
        $total = ($end_time - $start_time) / 86400 + 1;
        $data_list = [];
        for ($i = ($total - 1); $i >= 0; $i--) {
            $day = date('Y-m-d', $start_time + ($i * 86400));
            $data = $default_data;
            $data['day'] = $day;
            if (isset($userData[$day])) {
                $data = array_merge($data, $userData[$day]);
            }

            if (isset($adEventData[$day])) {
                $data = array_merge($data, $adEventData[$day]);
            }

            $data_list[] = $data;
        }

        foreach ($data_list as &$val) {
            //填充率
            $val['total_full_rate'] = $val['total_request_num'] > 0 ? round($val['total_full_num'] / $val['total_request_num'] * 100, 2) . '%' : '--';
            //展现率
            $val['total_show_rate'] = $val['total_full_num'] > 0 ? round($val['total_show_num'] / $val['total_full_num'] * 100, 2) . '%' : '--';
            //总人均
            $val['total_person_rate'] = $val['user_num'] > 0 ? round($val['total_show_num'] / $val['user_num'], 2) : '--';
            //体内人均
            $val['in_person_rate'] = $val['user_num'] > 0 ? round($val['in_show_num'] / $val['user_num'], 2) : '--';
            //体内填充率
            $val['in_full_rate'] = $val['in_request_num'] > 0 ? round($val['in_full_num'] / $val['in_request_num'] * 100, 2) . '%' : '--';
            //体内展现率
            $val['in_show_rate'] = $val['in_full_num'] > 0 ? round($val['in_show_num'] / $val['in_full_num'] * 100, 2) . '%' : '--';
            //体外人均
            $val['out_person_rate'] = $val['user_num'] > 0 ? round($val['out_show_num'] / $val['user_num'], 2) : '--';
            //体外填充率
            $val['out_full_rate'] = $val['out_request_num'] > 0 ? round($val['out_full_num'] / $val['out_request_num'] * 100, 2) . '%' : '--';
            //体外展现率
            $val['out_show_rate'] = $val['out_full_num'] > 0 ? round($val['out_show_num'] / $val['out_full_num'] * 100, 2) . '%' : '--';
        }
        return $data_list;
    }


}
