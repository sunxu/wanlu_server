<?php

namespace App\Console\Commands\Black;

use App\Services\Common\App;
use App\Services\Helper\Helper;
use DB;
use Illuminate\Console\Command;

class AttributeBlackList extends Command
{
    /**
     * @var string Command Name
     */
    protected $signature = "attribute-black-list {date}";

    /**
     * Command description
     * @var string
     */
    protected $description = "归因-黑名单-数据列表";

    /**
     * Command Handle
     */
    public function handle()
    {
        $date = $this->argument('date');
        $start_time = strtotime($date);
        $end_time = $start_time + 86400;
        // 工具
        $appsAgency = App::codeList([data_get(App::$appType, 'tool')], [], [data_get(App::$adStatus, 'agency')]);
        $apps = App::codeList([data_get(App::$appType, 'tool')]);

        foreach ($apps as $appcode => $appname) {
            if (isset($appsAgency[$appcode])) {
                //归因数
                $attr_list = DB::table("app_{$appcode}")
                    ->where('create_time', '>', $start_time)
                    ->where('create_time', '<=', $end_time)
                    ->select([
                        DB::raw("FROM_UNIXTIME(create_time,'%Y-%m-%d') as date"),
                        DB::raw("COUNT(*) as attr_num")
                    ])
                    ->groupBy('date')
                    ->get()
                    ->toArray();
                //黑名单数
                $black_list = DB::table('app_' . $appcode . ' as a')
                    ->leftJoin(
                        "app_{$appcode}_log as b", function ($join) {
                        $join->on('a.android_id', '=', 'b.android_id')
                            ->whereRaw("FROM_UNIXTIME(a.create_time, '%Y%m%d') = FROM_UNIXTIME(b.create_time, '%Y%m%d' )");
                    })
                    ->where('a.create_time', '>', $start_time)
                    ->where('a.create_time', '<=', $end_time)
                    ->where('b.create_time', '>', $start_time)
                    ->where('b.create_time', '<=', $end_time)
                    ->where('b.strategy_target', '=', 'black')
                    ->select([
                        DB::raw("FROM_UNIXTIME(a.create_time,'%Y-%m-%d') as date"),
                        DB::raw("COUNT(distinct a. id) as black_num")
                    ])
                    ->groupBy('date')
                    ->get()
                    ->toArray();
            } else {
                //黑名单数
                $black_list = DB::table("app_{$appcode}_log")
                    ->where('create_time', '>', $start_time)
                    ->where('create_time', '<=', $end_time)
                    ->where('strategy_target', '=', 'promotion_ad_black')
                    ->select([
                        DB::raw("FROM_UNIXTIME(create_time,'%Y-%m-%d') as date"),
                        DB::raw("COUNT(DISTINCT android_id) as black_num")
                    ])
                    ->groupBy('date')
                    ->get()
                    ->toArray();
                //归因数
                $attr_list = DB::table("app_{$appcode}")
                    ->where('ad_time', '>', $start_time)
                    ->where('ad_time', '<=', $end_time)
                    ->select([
                        DB::raw("FROM_UNIXTIME(ad_time,'%Y-%m-%d') as date"),
                        DB::raw("COUNT(*) as attr_num")
                    ])
                    ->groupBy('date')
                    ->get()
                    ->toArray();
            }
            foreach ($black_list as $k1 => &$v1) {
                foreach ($attr_list as $k2 => $v2) {
                    if ($v1['date'] == $v2['date']) {
                        $v1['attr_num'] = $v2['attr_num'];
                    } else {
                        continue;
                    }
                }
            }
            foreach ($black_list as $k3 => $v3) {
                $input = [
                    'app_code' => $appcode,
                    'date' => $v3['date'],
                    'attribution_num' => $v3['attr_num'] ?? 0,
                    'black_num' => $v3['black_num'] ?? 0,
                    'create_time' => time()
                ];
                $attributes = [
                    'app_code' => $input['app_code'],
                    'date' => $input['date']
                ];
                $insert = DB::table('blacklist_log')->updateOrInsert($attributes, $input);
                if ($insert) {
                    echo "插入成功:应用:" . $appcode . '-' . '日期:' . $v3['date'];
                    echo "\r\n";
                } else {
                    echo "插入失败:应用:" . $appcode . '-' . '日期:' . $v3['date'];
                    echo "\r\n";
                }
            }
        }

    }
}
