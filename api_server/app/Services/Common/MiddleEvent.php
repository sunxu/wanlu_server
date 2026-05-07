<?php

declare(strict_types=1);
/**
 * 广告中间表数据计算
 */

namespace App\Services\Common;

use Illuminate\Support\Facades\DB;

class MiddleEvent
{
    private $appcode;
    public $date;
    private static $instance = null;

    /**
     * @return MiddleEvent|null
     */
    public static function getInstance(): ?MiddleEvent
    {
        if (self::$instance == null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * 设置appcode
     * @param string $appcode
     * @return MiddleEvent
     */
    public function setAppCode(string $appcode): MiddleEvent
    {
        $this->appcode = $appcode;
        return $this;
    }

    /**
     * 设置date
     * @param string $day_time
     * @return MiddleEvent
     */
    public function setDate(string $day_time): MiddleEvent
    {
        $this->date = $day_time;
        return $this;
    }

    public function findAll()
    {
        $date = $this->date;
        $appcode = $this->appcode;
        $start_time = strtotime($date);
        $end_time = strtotime($date) + 86400;

        $eventArr = ['request', 'full', 'show'];
        $inside = config('api.inside_new');
        $date = $start_time;

        $diff = time() - $start_time;
        $diff_day = floor($diff / 86400);

        $device_table = "app_" . $appcode;
        foreach ($eventArr as $event) {
            //打点表，超过2天的数据在_temp表中
            $event_ad_table = $diff_day <= 2 ? "app_" . $appcode . "_event_ad_" . $event : "app_" . $appcode . "_event_ad_" . $event . '_temp';
            //新增安装打点数
            $insertData = DB::table("{$event_ad_table} as a")
                ->leftJoin("{$device_table} as b", 'a.android_id', '=', 'b.android_id')
                ->select(['a.version', 'a.vendor', 'a.page', 'a.channel',
                    DB::raw("sum(a.times) as request_num")])
                ->where('a.create_time', '>=', $start_time)
                ->where('a.create_time', '<', $end_time)
                ->where('b.create_time', '>=', $start_time)
                ->where('b.create_time', '<', $end_time)
                ->groupby(['a.version', 'a.channel', 'a.vendor', 'a.page'])->get()->toArray();
            $this->insertMiddle($insertData, $event, $inside, $date, 1, $appcode);

            //dau、deu打点数
            $dauDeuData = DB::table("{$event_ad_table} as a")
                ->select(['a.version', 'a.vendor', 'a.page', 'a.channel',
                    DB::raw("sum(a.times) as request_num")])
                ->where('a.create_time', '>=', $start_time)
                ->where('a.create_time', '<', $end_time)
                ->groupby(['a.version', 'a.channel', 'a.vendor', 'a.page'])->get()->toArray();
            $this->insertMiddle($dauDeuData, $event, $inside, $date, 2, $appcode);
            $this->insertMiddle($dauDeuData, $event, $inside, $date, 5, $appcode);

            //新增激活打点数
            $activeData = DB::table("{$event_ad_table} as a")
                ->leftJoin("{$device_table} as b", 'a.android_id', '=', 'b.android_id')
                ->select(['a.version', 'a.vendor', 'a.page', 'a.channel',
                    DB::raw("sum(a.times) as request_num")])
                ->where('a.create_time', '>=', $start_time)
                ->where('a.create_time', '<', $end_time)
                ->where('b.active_time', '>=', $start_time)
                ->where('b.active_time', '<', $end_time)
                ->groupby(['a.version', 'a.channel', 'a.vendor', 'a.page'])->get()->toArray();
            $this->insertMiddle($activeData, $event, $inside, $date, 3, $appcode);

            //deu老打点数
            $deuOldData = DB::table("{$event_ad_table} as a")
                ->leftJoin("{$device_table} as b", 'a.android_id', '=', 'b.android_id')
                ->select(['a.version', 'a.vendor', 'a.page', 'a.channel',
                    DB::raw("sum(a.times) as request_num")])
                ->where('a.create_time', '>=', $start_time)
                ->where('a.create_time', '<', $end_time)
                ->where('b.create_time', '<', $start_time)
                ->groupby(['a.version', 'a.channel', 'a.vendor', 'a.page'])->get()->toArray();
            $this->insertMiddle($deuOldData, $event, $inside, $date, 4, $appcode);
        }

        //--------------------------------------------------
        $active_keep_table = 'app_' . $appcode . '_active_keep';
        $day = (int)date('Ymd', $start_time);
        //新增安装用户数
        $insertUserData = DB::table($active_keep_table . ' as a')
            ->rightJoin($device_table . ' as b', 'a.device_id', '=', 'b.android_id')
            ->select(['b.version', 'b.channel', 'b.vendor', DB::raw("count(*) as user_num")])
            ->where('a.install_day', $day)
            ->where('a.install_keep_num', 0)
            ->groupby(['b.version', 'b.channel', 'b.vendor'])->get()->toArray();
        $this->insertUserMiddle($insertUserData, $date, 1, $appcode);

        //deu用户数
        $deuUserData = DB::table($active_keep_table . ' as a')
            ->rightJoin($device_table . ' as b', 'a.device_id', '=', 'b.android_id')
            ->select(['b.version', 'b.channel', 'b.vendor', DB::raw("count(*) as user_num")])
            ->where('a.active_day', $day)
            ->where('a.is_ad_report', 1)
            ->groupby(['b.version', 'b.channel', 'b.vendor'])->get()->toArray();
        $this->insertUserMiddle($deuUserData, $date, 2, $appcode);

        //新增激活用户数
        $activateUserData = DB::table($active_keep_table . ' as a')
            ->rightJoin($device_table . ' as b', 'a.device_id', '=', 'b.android_id')
            ->select(['b.version', 'b.channel', 'b.vendor', DB::raw("count(*) as user_num")])
            ->where('a.activate_day', $day)
            ->where('a.activate_keep_num', 0)
            ->groupby(['b.version', 'b.channel', 'b.vendor'])->get()->toArray();
        $this->insertUserMiddle($activateUserData, $date, 3, $appcode);

        //deu老用户数
        $deuOldUserData = DB::table($active_keep_table . ' as a')
            ->rightJoin($device_table . ' as b', 'a.device_id', '=', 'b.android_id')
            ->select(['b.version', 'b.channel', 'b.vendor', DB::raw("count(*) as user_num")])
            ->where('a.active_day', $day)
            ->where('a.is_ad_report', 1)
            ->where('a.install_keep_num', '>', 0)
            ->groupby(['b.version', 'b.channel', 'b.vendor'])->get()->toArray();
        $this->insertUserMiddle($deuOldUserData, $date, 4, $appcode);

        //DAU用户
        $dauUserData = DB::table($active_keep_table . ' as a')
            ->rightJoin($device_table . ' as b', 'a.device_id', '=', 'b.android_id')
            ->select(['b.version', 'b.channel', 'b.vendor', DB::raw("count(*) as user_num")])
            ->where('a.active_day', $day)
            ->groupby(['b.version', 'b.channel', 'b.vendor'])->get()->toArray();
        $this->insertUserMiddle($dauUserData, $date, 5, $appcode);
    }

    protected function insertMiddle($data, $event, $inside, $date, $userType, $appcode)
    {
        $date = date('Y-m-d', $date);
        $data = json_decode(json_encode($data), true);
        if (empty($data)) {
            return date('Y-m-d H:i:s') . '无数据' . $appcode . '-' . $event;
        }

        foreach ($data as $k => $v) {
            $key_arr = [];
            if (!empty($v['page'])) $key_arr = explode(":", $v['page']);
            switch (count($key_arr)) {
                case 1:
                case 2:
                case 3:
                    $is_inside = 2;
                    if (in_array($key_arr[0], $inside)) $is_inside = 1;
                    $ad_scone = $key_arr[0] ?? '';
                    $ad_type = $key_arr[0] ?? '';
                    break;
                case 4:
                    $is_inside = 2;
                    if (in_array($key_arr[1], $inside)) $is_inside = 1;
                    $ad_scone = $key_arr[1];
                    $ad_type = $key_arr[3] ?? '';
                    break;
                default:
                    $is_inside = 2;
                    $ad_scone = $v['page'] ?? '';
                    $ad_type = $v['page'] ?? '';
                    break;
            }

            $ad_type = $key_arr[count($key_arr) - 1] ?? '';
            $input = [
                'app_code' => $appcode ?? '',
                'event' => $event ?? '',
                'version' => $v['version'] ?? '',
                'channel' => $v['channel'] ?? '',
                'vendor' => $v['vendor'] ?? '',
                'page' => $v['page'] ?? '',
                'ad_scene' => $ad_scone,
                'ad_type' => $ad_type,
                'date' => $date ?? 0,
                'is_new' => $userType,
                'is_inside' => $is_inside,
                'request_num' => $v['request_num'] ?? 0,
                'create_time' => time()
            ];
            $attributes = [
                'app_code' => $appcode,
                'event' => $event,
                'version' => $input['version'],
                'channel' => $input['channel'],
                'vendor' => $input['vendor'],
                'page' => $input['page'],
                'date' => $date,
                'is_new' => $input['is_new'],
                'is_inside' => $input['is_inside']
            ];
            $insert = DB::table('app_middle_event_ad_new')->updateOrInsert($attributes, $input);
            if (!$insert) {
                echo date('Y-m-d H:i:s') . "没有数据更新:" . $appcode . '-' . $event;
                echo "\r\n";
            }
        }
        echo date('Y-m-d H:i:s') . "数据更新成功:" . $appcode . '-' . $event;
        echo "\r\n";
    }

    protected function insertUserMiddle($data, $date, $userType, $appcode)
    {
        $date = date('Y-m-d', $date);
        $data = json_decode(json_encode($data), true);
        if (empty($data)) {
            return date('Y-m-d H:i:s') . '用户无数据' . $appcode;
        }
        foreach ($data as $k => $v) {
            $input = [
                'app_code' => $appcode ?? '',
                'version' => $v['version'] ?? '',
                'channel' => $v['channel'] ?? '',
                'vendor' => $v['vendor'] ?? '',
                'date' => $date ?? 0,
                'is_new' => $userType,
                'user_num' => $v['user_num'] ?? 0,
                'create_time' => time()
            ];
            $attributes = [
                'app_code' => $appcode,
                'version' => $input['version'],
                'channel' => $input['channel'],
                'vendor' => $input['vendor'],
                'date' => $date,
                'is_new' => $input['is_new']
            ];
            $insert = DB::table('middle_event_user_new')->updateOrInsert($attributes, $input);
            if (!$insert) {
                echo "失败:" . $appcode;
                echo "\r\n";
            }
        }
        echo date('Y-m-d H:i:s') . "数据更新成功:" . $appcode;
        echo "\r\n";
    }


}
