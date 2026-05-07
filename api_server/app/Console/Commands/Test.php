<?php
// 测试
namespace App\Console\Commands;

use App\Services\Common\App;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Schema;

class Test extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'test {appcode?*}';

    /**
     * Command description
     * @var string
     */
    protected $description = 'php artisan test';

    /**
     * Command Handle
     */
    public function handle()
    {
        $arguments = $this->arguments();
        $appNum = DB::connection('uc')->table('app')
            ->count('app_id');
        var_dump($appNum);
        $defaultNum = DB::table('app_default')
            ->count('id');
        var_dump($defaultNum);
    }

    public function delAppTable()
    {
        $appCodes = DB::connection('uc')->table('app')
            ->where('status', 0)
            ->pluck('app_code')
            ->toArray();
        // 创建应用表
        $data = DB::select('show tables');
        foreach ($data as $v) {
            foreach ($v as $v2) {
                foreach ($appCodes as $code) {
                    if (strstr($v2, "app_{$code}")) {
                        $table = str_replace("{$code}", $code, $v2);
                        DB::statement("
                            DROP TABLE IF EXISTS {$v2}
                        ");
                        echo $table . PHP_EOL;
                    }
                }
            }
        }
    }

    protected function addAppTable($copy = 'default')
    {
        $appCodes = DB::connection('uc')->table('app')
            ->where('status', 1)
            ->pluck('app_code')
            ->toArray();
        // 创建应用表
        $data = DB::select('show tables');
        foreach ($data as $v) {
            foreach ($v as $v2) {
                foreach ($appCodes as $code) {
                    if (strstr($v2, "app_{$copy}")) {
                        $table = str_replace("{$copy}", $code, $v2);
                        DB::statement("
                            CREATE TABLE IF NOT EXISTS {$table} LIKE {$v2}
                        ");
                        echo $table . PHP_EOL;
                    }
                }
            }
        }
    }

    public function devieNum()
    {
        $codeList = [
            'ngwbx' => '南瓜万宝箱',
            'mghz' => '没罐盒子',
            'sdfdj' => '善读放大镜',
            'mqhz' => '麻雀盒子',
            'mnxh' => '迷你小盒',
            'gtjz' => '光头记账',
            'llwbx' => '玲珑万宝箱',
            'yhdk' => '硬核打卡',
            'bmhz' => '布忙盒子',
            'zdgjx' => '周到工具箱',
            'mxdd' => '木匣袋袋',
            'dzlql' => '大字体浏览器',
            'kss' => '快嗖嗖',
            'shoujia' => '收夹',
        ];
        $limit = 2;
        foreach ($codeList as $code => $name) {
            echo $code . PHP_EOL;
            for ($date = '2022-12-29'; strtotime($date) < strtotime('2023-01-13'); $date = date('Y-m-d', strtotime('+1 days', strtotime($date)))) {
                echo $date . PHP_EOL;
                $ipList = DB::table('temp_0113')
                    ->where('date', $date)
                    ->where('app_code', $code)
                    ->select([
                        'app_code',
                        'app_name',
                        'date',
                        'ip',
                        DB::raw("count(distinct android_id) as ip_device")
                    ])
                    ->groupBy('ip')
                    ->orderBy('ip_device', 'desc')
                    ->limit($limit)
                    ->get()
                    ->toArray();
                $modelList = DB::table('temp_0113')
                    ->where('date', $date)
                    ->where('app_code', $code)
                    ->select([
                        'app_code',
                        'app_name',
                        'date',
                        'model',
                        DB::raw("count(distinct android_id) as model_device")
                    ])
                    ->groupBy('model')
                    ->orderBy('model_device', 'desc')
                    ->limit($limit)
                    ->get()
                    ->toArray();
                foreach ($ipList as $k => $v) {
                    $ipList[$k] = array_merge($modelList[$k] ?? [
                            'model' => '',
                            'model_device' => 0,
                        ], $v);
                }

                DB::table('temp_0113_device_2')->insertOrIgnore($ipList);
            }
        }
    }

    /**
     * 变现异常深度排查
     */
    public function report()
    {
        $codeList = [
            'yztq' => '预知天气通',
        ];

        $table = 'temp_0531';

        foreach ($codeList as $code => $name) {
            echo $name . PHP_EOL;
            for ($date = date('Y-m-d', strtotime('-15 days')); strtotime($date) < strtotime(date('Y-m-d')); $date = date('Y-m-d', strtotime('+1 days', strtotime($date)))) {
                $temp = strtotime($date) < strtotime(date('Y-m-d', strtotime('-1 days'))) ? '_temp' : '';
                $timestampStart = strtotime($date);
                $timestampEnd = strtotime($date . ' 23:59:59');
                echo $date . 'start' . PHP_EOL;
//                if (DB::table($table)->where('app_code', $code)->where('date', $date)->first()) {
//                    continue;
//                }
                DB::statement("
                    insert ignore into  {$table}
                    SELECT
                        '{$code}' as app_code,
                        '{$name}' as app_name,
                        '{$date}' as date,
                        app.android_id,
                        app.oaid,
                        app.imei,
                        app.vendor,
                        app.model,
                        app.ip,
                        FROM_UNIXTIME( app.active_time ) AS activate_time,
                        app.account_id AS advertiser_id,
                        app.aid AS plan_promotion_id,
                        FROM_UNIXTIME( if(ISNULL(ad.id),_ad.ts,ad.ts) ) AS click_time,
                        if(ISNULL(ad.id),_ad.callback_url,ad.callback_url) AS click_id,
                        count(_show.uuid) as show_num,
						sum(_show.info->>'$.realEcpm') /count(_show.uuid)  as show_ecpm
                    FROM
                        app_{$code} AS app
                        LEFT JOIN app_{$code}_event_ad_show{$temp} as _show ON _show.android_id = app.android_id
                        LEFT JOIN app_{$code}_ad AS ad ON app.ad_id = ad.id
                        LEFT JOIN app_{$code}_ad_temp AS _ad ON app.ad_id = _ad.id
                    WHERE
                        app.active_time > 0
                        AND _show.create_time >= {$timestampStart} and _show.create_time < {$timestampEnd}
                        AND _show.info->>'$.networkPlacementId' IN (
                                                        '2005934994812489',
                                                        '9035538944011728',
                                                        '9075232934012832',
                                                        '4025234934818472',
                                                        '9005337984914642',
                                                        '4055036914210992'
                                                        )
                    GROUP BY app.android_id
                ");

                DB::statement("
                    insert ignore into  {$table}
                    SELECT
                        '{$code}' as app_code,
                        '{$name}' as app_name,
                        '{$date}' as date,
                        app.android_id,
                        app.oaid,
                        app.imei,
                        app.vendor,
                        app.model,
                        app.ip,
                        FROM_UNIXTIME( app.active_time ) AS activate_time,
                        app.account_id AS advertiser_id,
                        app.aid AS plan_promotion_id,
                        FROM_UNIXTIME( if(ISNULL(ad.id),_ad.ts,ad.ts) ) AS click_time,
                        if(ISNULL(ad.id),_ad.callback_url,ad.callback_url) AS click_id,
                        count(_show.uuid) as show_num,
						sum(_show.info->>'$.realEcpm') /count(_show.uuid)  as show_ecpm
                    FROM
                        app_{$code}_temp AS app
                        LEFT JOIN app_{$code}_event_ad_show{$temp} as _show ON _show.android_id = app.android_id
                        LEFT JOIN app_{$code}_ad AS ad ON app.ad_id = ad.id
                        LEFT JOIN app_{$code}_ad_temp AS _ad ON app.ad_id = _ad.id
                    WHERE
                        app.active_time > 0
                        AND _show.create_time >= {$timestampStart} and _show.create_time < {$timestampEnd}
                        AND _show.info->>'$.networkPlacementId' IN (
                                                        '2005934994812489',
                                                        '9035538944011728',
                                                        '9075232934012832',
                                                        '4025234934818472',
                                                        '9005337984914642',
                                                        '4055036914210992'
                                                        )
                    GROUP BY app.android_id
                ");
                echo $date . 'end' . PHP_EOL;
            }
        }
    }

    public function updateTemp0131()
    {
        $list = DB::table('temp_0131')
            ->select(['app_code', 'android_id'])
            ->whereNull('advertiser_id')
            ->groupBy('android_id')
            ->get()
            ->toArray();
        foreach ($list as $v) {
            dump($v);
            $adId = DB::table("app_{$v['app_code']}")
                ->where('android_id', $v['android_id'])
                ->value('ad_id');
            dump($adId);
            if (empty($adId)) {
                continue;
            }
            $ad = DB::table("app_{$v['app_code']}_ad_temp")
                ->select([
                    'account_id',
                    'aid',
                    'ts',
                    'callback_url',
                ])
                ->where('id', $adId)
                ->first();

            if (empty($ad)) {
                continue;
            }
            $result = DB::table('temp_0131')
                ->where('android_id', $v['android_id'])
                ->update([
                    'advertiser_id' => $ad['account_id'],
                    'plan_promotion_id' => $ad['aid'],
                    'click_time' => date('Y-m-d H:i:s', $ad['ts']),
                    'click_id' => $ad['callback_url'],
                ]);
            dump($result);
        }

    }

    public function adEcpm()
    {
        $codeList = [
//            'ngwbx' => '南瓜万宝箱',
//            'mghz' => '没罐盒子',
//            'sdfdj' => '善读放大镜',
//            'mqhz' => '麻雀盒子',
//            'mnxh' => '迷你小盒',
//            'gtjz' => '光头记账',
//            'llwbx' => '玲珑万宝箱',
//            'yhdk' => '硬核打卡',
//            'bmhz' => '布忙盒子',
//            'zdgjx' => '周到工具箱',
//            'mxdd' => '木匣袋袋',
//            'dzlql' => '大字体浏览器',
//            'kss' => '快嗖嗖',
//            'shoujia' => '收夹',
            'skqcl' => '山葵轻处理',
            'mmgjj' => '秒秒工具集',
        ];

        $table = 'temp_ad_0131';

        foreach ($codeList as $code => $name) {
            echo $name . PHP_EOL;
            DB::statement("
                    insert ignore into  {$table}
                    SELECT
                        '{$code}' as app_code,
                        '{$name}' as app_name,
                        FROM_UNIXTIME( create_time, '%Y-%m-%d' ) as date,
                        android_id,
                        vendor,
                        model,
                        info ->> '$.adType' AS ad_type,
	                    count(*) as show_num,
	                    round(sum( info ->> '$.realEcpm' ) / count(*),4) AS show_ecpm 
                    FROM
                        app_{$code}_event_ad_show
                    GROUP BY date,android_id,ad_type
                ");
            echo 'end' . PHP_EOL;
        }
    }

    public function active()
    {
        $codeList = [
            'ykbd' => '乙口布袋',
            'skqcl' => '山葵轻处理',
            'lqhz' => '厘晴盒子',
            'mmgjj' => '秒秒工具集',
            'bjqgj' => '部件儿轻工具',
        ];

        $table = 'temp_0201';

        foreach ($codeList as $code => $name) {
            echo $name . PHP_EOL;
            for ($date = date('Y-m-d', strtotime('-15 days')); strtotime($date) < strtotime(date('Y-m-d')); $date = date('Y-m-d', strtotime('+1 days', strtotime($date)))) {
                $temp = strtotime($date) < strtotime(date('Y-m-d', strtotime('-2 days'))) ? '_temp' : '';
                $timestampStart = strtotime($date);
                $timestampEnd = strtotime($date . ' 23:59:59');
                echo $date . 'start' . PHP_EOL;
                if (DB::table($table)->where('app_name', $name)->where('date', $date)->first()) {
                    continue;
                }
                DB::statement("
                    insert into  {$table}
                    SELECT
                        '{$name}' as app_name,
                        '{$date}' as date,
                        vendor,
                        model,
                        null as active_time,
                        null as active_device,
                        count(*)/count(distinct android_id) as show_num,
                        count(distinct android_id) as show_device
                    FROM
                        app_{$code}_event_ad_show{$temp}
                    WHERE
                        create_time >= {$timestampStart} and create_time < {$timestampEnd}
                    GROUP BY model
                    ON DUPLICATE KEY UPDATE 
                        show_num = VALUES ( show_num ),
                        show_device = VALUES ( show_device )
                ");
                DB::statement("
                    insert into  {$table}
                    SELECT
                        '{$name}' as app_name,
                        '{$date}' as date,
                        vendor,
                        model,
                        sum(time)/60000/count(distinct android_id) as active_time,
                        count(distinct android_id) as active_device,
                        null as show_num,
                        null as show_device
                    FROM
                        app_{$code}_active{$temp}
                    WHERE
                        create_time >= {$timestampStart} and create_time < {$timestampEnd}
                    GROUP BY model
                    ON DUPLICATE KEY UPDATE 
                        active_time = VALUES ( active_time ),
                        active_device = VALUES ( active_device )
                ");
                echo $date . 'end' . PHP_EOL;
            }
        }
    }

    public function userAdShow()
    {
        $codeList = [
            'skqcl' => '山葵轻处理',
            'mmgjj' => '秒秒工具集',
        ];

        $table = 'temp_ad_0202';

        foreach ($codeList as $code => $name) {
            echo $name . PHP_EOL;
            DB::statement("
                    insert ignore into  {$table}
                    SELECT
                        '{$code}' AS app_code,
                        '{$name}' AS app_name,
                        FROM_UNIXTIME( create_time, '%Y%m%d%H' ) AS time,
                        android_id,
                        vendor,
                        model,
                        info ->> '$.adType' AS ad_type,
                        case LENGTH(info->>'$.networkPlacementId')
                            when 9 then 'csj'
                            when 16 then 'ylh'
                            else 'ks'
                        end as media,
                        count(*) AS show_num,
                        round( sum( info ->> '$.realEcpm' ) / count(*), 4 ) AS show_ecpm 
                    FROM
                        app_{$code}_event_ad_show_temp
                    GROUP BY
                        time,
                        android_id,
                        media,
                        ad_type
                ");
            echo 'end' . PHP_EOL;
        }
    }

    public function blackListRevenue()
    {
        $codeList = DB::connection('uc')
            ->table('app')
            ->pluck('app_name', 'app_code')
            ->toArray();

        $table = 'temp_oaid_0206';

        foreach ($codeList as $code => $name) {
            echo $name . PHP_EOL;
            for ($date = date('Y-m-d', strtotime('2023-01-29')); strtotime($date) <= strtotime('-7 days'); $date = date('Y-m-d', strtotime('+1 days', strtotime($date)))) {
                $temp = strtotime($date) < strtotime(date('Y-m-d', strtotime('-30 days'))) ? '_temp' : '';
                $timestampStart = strtotime($date);
                $timestampEnd = strtotime($date . ' 23:59:59');

                $tableRevenue = "app_{$code}_event_interval_group" . $temp;
                // 停止投放跳过
                if (!Schema::hasTable($tableRevenue) || !DB::table("app_{$code}_event_interval_group" . $temp)
                        ->where('start_time', '>=', $timestampStart)
                        ->where('end_time', '<=', $timestampEnd)
                        ->value('id')) {
                    continue;
                }

                echo $date . 'start' . PHP_EOL;

                DB::statement("
                    INSERT IGNORE INTO {$table}
                    SELECT 
                        NULL AS id,
                        '{$code}' AS app_code,
                        a.android_id,
                        a.oaid,
                        SUM(b.amount) as amount
                    FROM
                        app_{$code}{$temp} as a
                        LEFT JOIN app_{$code}_event_interval_group{$temp} as b ON b.device_id = a.android_id
                    WHERE
                        a.oaid != ''
                        AND LOCATE('oaid',a.android_id)=0
                        AND a.create_time >= $timestampStart
                        AND a.create_time < $timestampEnd
                    GROUP BY a.android_id
                    HAVING amount > 0
                ");
                echo 'end' . PHP_EOL;
            }
        }
    }


    public function adClick()
    {
        $codeList = DB::connection('uc')
            ->table('app')
            ->pluck('app_name', 'app_code')
            ->toArray();

        foreach ($codeList as $code => $name) {
            echo $name . PHP_EOL;
            if (!App::hasApp($code)) {
                continue;
            }
            DB::statement("
                TRUNCATE `app_{$code}_event_ad_click`
            ");
            DB::statement("
                TRUNCATE `app_{$code}_event_ad_click_temp`
            ");
        }
    }

    // 有广告无策略人群
    public function exceptionDevice()
    {
        $codeList = [
            'ngwbx' => '南瓜万宝箱',
            'mghz' => '没罐盒子',
            'sdfdj' => '善读放大镜',
            'mqhz' => '麻雀盒子',
            'mnxh' => '迷你小盒',
            'gtjz' => '光头记账',
            'llwbx' => '玲珑万宝箱',
            'yhdk' => '硬核打卡',
            'bmhz' => '布忙盒子',
            'zdgjx' => '周到工具箱',
            'mxdd' => '木匣袋袋',
            'dzlql' => '大字体浏览器',
            'kss' => '快嗖嗖',
            'shoujia' => '收夹',
        ];

        $table = 'temp_0217';

        foreach ($codeList as $code => $name) {
            echo $name . PHP_EOL;
            DB::statement("
                    insert ignore into {$table}
                    select null as id,'{$code}',a.android_id,a.oaid 
                    from app_{$code} as a 
                    where a.android_id in (
                        select distinct android_id from app_{$code}_event_ad_show
                    )
                    and not EXISTS(
                        select 1 from app_{$code}_log as b where b.android_id =a.android_id
                    )
                    and not EXISTS(
                        select 1 from app_{$code}_log_temp as c where c.android_id =a.android_id
                    )
                    HAVING oaid != ''
                ");
            echo 'end' . PHP_EOL;
        }
    }

    public function addTable()
    {
        $apps = App::codeList([data_get(App::$appType, 'tool')]);

        foreach ($apps as $code => $name) {
            if (!App::hasApp($code)) {
                continue;
            }
            $table = "app_{$code}_event_ad_revenue";
            $tableTemp = "app_{$code}_event_ad_revenue_temp";

            if (Schema::hasTable($table)) {
                DB::statement("
                    ALTER TABLE {$table} 
                    ADD INDEX `idx_device`(`device_id`) USING BTREE;;
                ");
            }
            if (Schema::hasTable($tableTemp)) {
                DB::statement("
                    ALTER TABLE {$tableTemp} 
                    ADD INDEX `idx_device`(`device_id`) USING BTREE;;
                ");
            }
            echo $code . PHP_EOL;
        }
    }

    public function appRevenue($codes = [])
    {
        $apps = App::codeList([data_get(App::$appType, 'tool')], $codes, [
            data_get(App::$adStatus, 'self')
        ]);

        foreach ($apps as $code => $name) {
            if ($code == 'yfmgj') {
                continue;
            }

            for ($time = strtotime('2023-04-17 15:30:00'); $time < strtotime('2023-04-18 13:30:00'); $time += 600) {
                $start = $time;
                $end = $time + 600;
                $list = DB::table(App::tableEventAdShow($code))
                    ->select([
                        DB::raw("SUM(info->>'$.realEcpm')/1000 as amount"),
                        "android_id as device_id",
                        DB::raw("MAX(info->>'$.level') as max_level")
                    ])
                    ->where('create_time', '>=', $start)
                    ->where('create_time', '<', $end)
                    ->groupBy('android_id')
                    ->get()
                    ->toArray();

                foreach ($list as $v) {
                    DB::table(App::tableIntervalGroup($code))
                        ->updateOrInsert([
                            'device_id' => $v['device_id'],
                            'start_time' => $start,
                            'end_time' => $end,
                        ], [
                            'event_type' => 'ad_show',
                            'start_end' => date('Y-m-d H:i:s', $start) . '~' . date('Y-m-d H:i:s', $end),
                            'max_level' => $v['max_level'] ?? 0,
                            'amount' => $v['amount'],
                            'info' => '[]',
                            'create_time' => time()
                        ]);
                }

                dump([$code, date('YmdHis', $time)]);
            }

        }
    }
}
