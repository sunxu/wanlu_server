<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/7/15
 */

namespace App\Crontab;

use App\Model\App;
use App\Model\AppDailyDuration;
use App\Model\AppDailyShowKeep;
use Hyperf\DbConnection\Db;

class DailyDataCrontab
{
    public function execute($date = null)
    {
        $date = $date ?: date('Y-m-d', strtotime('-1 day'));
        $app_map = App::query()
            ->where('ad_status', 1)//自投产品
            ->where('type', 1000)//工具产品
            ->pluck('app_code')
            ->toArray();
        foreach ($app_map as $app_code) {
            echo $date . PHP_EOL;
            echo $app_code . PHP_EOL;

            /*人均展示*/
            $this->show_avg($app_code, $date);

            /*心跳时长*/
            $this->duration($app_code, $date);

            /*次留*/
            $this->keep($app_code, $date);


        }
    }

    public function show_avg($app_code, $date)
    {
        $db_tools = Db::connection('tools');
        $start = strtotime($date);
        $end = $start + 86400;
        $diff = time() - $start;
        $diff_day = floor($diff / 86400);

        $device_table = 'app_' . $app_code;//设备表
        $active_keep_table = 'app_' . $app_code . '_active_keep';//活跃留存表
        $ad_show_table = $diff_day <= 2 ? 'app_' . $app_code . '_event_ad_show' : 'app_' . $app_code . '_event_ad_show_temp';//广告展示表

        $sql = 'select 
                case a.vendor 
                when \'vivo\' then \'vivo\' 
                when \'oppo\' then \'oppo\'
                when \'realme\' then \'oppo\'
                when \'huawei\' then \'huawei\' 
                when \'honor\' then \'huawei\'
                when \'xiaomi\' then \'xiaomi\' 
                when \'redmi\' then \'xiaomi\' 
                else \'other\' end vendor_type,
                c.launch_type,
                count(distinct(a.android_id)) as `install`,
                sum(d.times) as `show`
                from ' . $device_table . ' as a 
                left join ' . $active_keep_table . ' as b on a.android_id = b.device_id
                left join advertisers as c on c.advertiser_id = a.account_id
                left join ' . $ad_show_table . ' as d on a.android_id = d.android_id
                where a.create_time >= ' . $start . ' and a.create_time < ' . $end . '
                and d.create_time >= ' . $start . ' and d.create_time < ' . $end . '
                and b.install_keep_num = 0
                and c.launch_type is not null
                group by vendor_type, c.launch_type';
        $data_list = $db_tools->select($sql);

        echo json_encode($data_list) . PHP_EOL;
        foreach ($data_list as $item) {
            $item = collect($item)->toArray();
            $item['created_time'] = time();
            $item['updated_time'] = time();
            $where = [];
            $where['date'] = $date;
            $where['app_code'] = $app_code;
            $where['launch_type'] = $item['launch_type'];
            $where['vendor_type'] = $item['vendor_type'];
            (new AppDailyShowKeep())->newQuery()->updateOrInsert($where, $item);
        }
    }

    public function duration($app_code, $date)
    {
        $db_tools = Db::connection('tools');
        $start = strtotime($date);
        $end = $start + 86400;

//        $diff = time() - $start;
//        $diff_day = floor($diff / 86400);
//        $duration_table = $diff_day <= 3 ? 'app_' . $app_code . '_active' : 'app_' . $app_code . '_active_temp';

        $duration_table = 'app_' . $app_code . '_active';

        /*最新版本*/
        $new_app_version = $db_tools->table($duration_table)
            ->select(['version', Db::raw('count(DISTINCT android_id) as total')])
            ->where('create_time', '>=', $start)
            ->where('create_time', '<', $end)
            ->groupBy(['version'])
            ->having('total', '>', 50)//活跃设备超过50台
            ->orderBy('version', 'desc')
            ->first();
        $new_app_version = data_get($new_app_version, 'version');
        $sql = 'select 
                    vendor_type,
                    app_version,
                    os_version_type, 
                    count(distinct(android_id)) as num,
                    sum(duration) as duration
                from (
                   SELECT
                        android_id,
                        version as app_version,
                        case vendor 
                        when \'vivo\' then \'vivo\' 
                        when \'oppo\' then \'oppo\'
                        when \'realme\' then \'oppo\'
                        when \'huawei\' then \'huawei\' 
                        when \'honor\' then \'huawei\' 
                        when \'xiaomi\' then \'xiaomi\' 
                        when \'redmi\' then \'xiaomi\' 
                        else \'other\' 
                        end vendor_type,
                        case 
                        when os_version < 8 then \'lt8\' 
                        when os_version >= 8 and os_version < 11 then \'ge8_lt11\'
                        when os_version >= 11 then \'ge11\' 
                        end os_version_type,
                        round( sum( time )/ 60000, 2 ) AS duration
                    FROM
                        ' . $duration_table . '
                    WHERE
                        create_time >= ' . $start . ' AND create_time < ' . $end . '
                    GROUP BY
                        android_id
                    HAVING
                        duration <= 24 * 60
                ) as tmp
                group by vendor_type,os_version_type,app_version';
        $data_list = $db_tools->select($sql);

        echo json_encode($data_list) . PHP_EOL;
        foreach ($data_list as $item) {
            $item = collect($item)->toArray();
            $item['created_time'] = time();
            $item['updated_time'] = time();
            $item['is_new_app_version'] = $item['app_version'] == $new_app_version ? 1 : 0;
            $where = [];
            $where['date'] = $date;
            $where['app_code'] = $app_code;
            $where['app_version'] = $item['app_version'];
            $where['vendor_type'] = $item['vendor_type'];
            $where['os_version_type'] = $item['os_version_type'];
            (new AppDailyDuration())->newQuery()->updateOrInsert($where, $item);
        }
    }

    public function keep($app_code, $date)
    {
        $date = date('Y-m-d', strtotime($date) - 86400);//前一天的数据
        $db_tools = Db::connection('tools');
        $start = strtotime($date);
        $end = $start + 86400;
        $device_table = 'app_' . $app_code;//设备表
        $active_keep_table = 'app_' . $app_code . '_active_keep';//活跃留存表

        $sql = 'select 
            case a.vendor 
            when \'vivo\' then \'vivo\' 
            when \'oppo\' then \'oppo\'
            when \'realme\' then \'oppo\'
            when \'huawei\' then \'huawei\' 
            when \'honor\' then \'huawei\'
            when \'xiaomi\' then \'xiaomi\' 
            when \'redmi\' then \'xiaomi\' 
            else \'other\' end vendor_type,
            c.launch_type,
            count(*) as keep
            from ' . $device_table . ' as a 
            left join ' . $active_keep_table . ' as b on a.android_id = b.device_id
            left join advertisers as c on c.advertiser_id = a.account_id
            where a.create_time >= ' . $start . ' and a.create_time < ' . $end . '
            and b.install_keep_num = 1
            and c.launch_type is not null 
            group by vendor_type, c.launch_type';
        $data_list = $db_tools->select($sql);

        echo json_encode($data_list) . PHP_EOL;
        foreach ($data_list as $item) {
            $item = collect($item)->toArray();
            $item['date'] = $date;
            $item['app_code'] = $app_code;
            /** @var AppDailyShowKeep $Model */
            $Model = AppDailyShowKeep::query()
                ->where('app_code', $app_code)
                ->where('date', $date)
                ->where('launch_type', $item['launch_type'])
                ->where('vendor_type', $item['vendor_type'])
                ->first();
            if ($Model) {
                $Model->keep = $item['keep'];
                $Model->save();
            }
        }
    }
}