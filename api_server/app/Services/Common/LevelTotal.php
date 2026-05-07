<?php

declare(strict_types=1);
/**
 * 广告分层数据统计
 * Author: wangshenyuan
 * Date: 2022/3/15
 */

namespace App\Services\Common;

use Illuminate\Support\Facades\DB;

class LevelTotal
{
    private $delay_time = 3600 * 2;//激活后延迟统计时长

    private $appcode;

    public $date;

    private static $instance = null;

    /**
     * @return LevelTotal|null
     */
    public static function getInstance(): ?LevelTotal
    {
        if (self::$instance == null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * 设置appcode
     * @param string $appcode
     * @return LevelTotal
     */
    public function setAppCode(string $appcode): LevelTotal
    {
        $this->appcode = $appcode;
        return $this;
    }

    /**
     * 设置date
     * @param int $day_time
     * @return LevelTotal
     */
    public function setDate(int $day_time): LevelTotal
    {
        $this->date = date('Y-m-d', $day_time);
        return $this;
    }

    public function total()
    {
        $start_time = strtotime($this->date);
        $end_time = $start_time + 86400;

        $tableDevice = App::tableDevice($this->appcode);
        $tableIntervalGroup = App::tableIntervalGroup($this->appcode);

        // 设备统计
        $install = DB::table($tableDevice)
            ->where('create_time', '>=', $start_time)
            ->where('create_time', '<', $end_time)
            ->count();//安装数
        $activate = DB::table($tableDevice)
            ->where('active_time', '>=', $start_time)
            ->where('active_time', '<', $end_time)
            ->count();//激活数

        //分层数据
        $where = [];
        $where['start_time'] = $start_time;
        $where['end_time'] = $end_time;
        $where['delay_time'] = $this->delay_time;
        $sub_sql = "SELECT MIN(`a`.`max_level`) AS `max_level` FROM `" . $tableIntervalGroup . "` AS `a` 
                    LEFT JOIN `" . $tableDevice . "` AS `b` ON `a`.`device_id` = `b`.`android_id` 
                    WHERE `b`.`active_time` >= :start_time
	                AND `b`.`active_time` < :end_time
	                AND `a`.`end_time` <= `b`.`active_time` + :delay_time
                    GROUP BY `a`.`device_id`";

        $sql = "SELECT ABS(`tmp`.`max_level`) AS `max_level`, COUNT(*) AS `count` FROM (" . $sub_sql . ")  AS `tmp` GROUP BY `tmp`.`max_level`";

        $result = DB::select($sql, $where);
        $max_level_list = [];
        foreach ($result as $row) {
            $max_level_list[$row['max_level']] = $row['count'];
        }


        $total = array_sum($max_level_list);//看广告总人数
        $level_none = $activate - $total;//无广告人数
        $level_0 = $max_level_list[0] ?? 0;//兜底层
        $level_1 = $max_level_list[1] ?? 0;//倒1层
        $level_2 = $max_level_list[2] ?? 0;//倒2层
        $level_3 = $max_level_list[3] ?? 0;//倒3层
        $level_4 = $max_level_list[4] ?? 0;//倒4层
        $level_5 = $max_level_list[5] ?? 0;//倒5层
        $level_404 = $max_level_list[404] ?? 0;//未设置层级
        $level_other = $total - $level_0 - $level_1 - $level_2 - $level_3 - $level_4 - $level_5 - $level_404;//其他层


        $insert = [
            'date' => $this->date,
            'app_code' => $this->appcode,
            'install' => $install,
            'activate' => $activate,
            'level_none' => $level_none,
            'level_0' => $level_0,
            'level_1' => $level_1,
            'level_2' => $level_2,
            'level_3' => $level_3,
            'level_4' => $level_4,
            'level_5' => $level_5,
            'level_404' => $level_404,
            'level_other' => $level_other,
            'update_time' => time()
        ];

        DB::table('ad_level_num')
            ->updateOrInsert([
                'date' => $this->date,
                'app_code' => $this->appcode
            ], $insert);
    }


}
