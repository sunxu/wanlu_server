<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property int $install_num 安装-数
 * @property int $install_next_day 安装-次留
 * @property string $install_next_day_rate 安装-次留率%
 * @property int $install_seven_day 安装-七日留存
 * @property string $install_seven_day_rate 安装-七日留存率%
 * @property int $install_request 安装-请求
 * @property int $install_fill 安装-填充
 * @property string $install_fill_rate 安装-填充率%
 * @property int $install_show 安装-展现
 * @property string $install_show_rate 安装-展现率%
 * @property string $install_avg_show 安装-人均展现
 * @property string $install_revenue 安装-预估收益
 * @property string $install_avg_revenue 安装-预估人均收益（ARPU）
 * @property string $install_roi 安装-ROI(收入/消耗)
 * @property string $install_revenue24 安装-24小时回收
 * @property string $install_keep 安装-留存
 * @property int $install_show_in 安装-展示数（体内）
 * @property int $install_show_out 安装-展示数（体外）
 * @property string $install_avg_show_in 安装-人均展示数（体内）
 * @property string $install_avg_show_out 安装-人均展示数（体外）
 * @property int $active_num 活跃-数
 * @property int $active_new 活跃-新增
 * @property int $active_request 活跃-请求
 * @property int $active_fill 活跃-填充
 * @property string $active_fill_rate 活跃-填充率%
 * @property int $active_show 活跃-展现
 * @property string $active_show_rate 活跃-展现率%
 * @property string $active_revenue 活跃-预估收益
 * @property int $activate_num 激活-数
 * @property int $activate_next_day 激活-次留
 * @property string $activate_next_day_rate 激活-次留率%
 * @property int $activate_seven_day 激活-七日留存
 * @property string $activate_seven_day_rate 激活-七日留存率%
 * @property int $activate_show 激活-展现
 * @property string $activate_avg_show 激活-人均展现
 * @property string $activate_revenue 激活-预估收益
 * @property string $activate_revenue24 激活-24小时回收
 * @property string $activate_keep 激活-留存
 * @property int $activate_show_in 激活-展示数（体内）
 * @property int $activate_show_out 激活-展示数（体外）
 * @property string $activate_avg_show_in 激活-人均展示数（体内）
 * @property string $activate_avg_show_out 激活-人均展示数（体外）
 * @property int $old_user_num 老户-数
 * @property int $old_show 老户-展示数
 * @property int $old_show_in 老户-展示数（体内）
 * @property int $old_show_out 老户-展示数（体外）
 * @property string $old_avg_show 老户-人均展示数
 * @property string $old_avg_show_in 老户-人均展示数（体内）
 * @property string $old_avg_show_out 老户-人均展示数（体外）
 * @property string $hour_revenue 小时-回收
 * @property string $recovery 回本率
 * @property string $withdraw 提现金额
 * @property string $hour_withdraw 小时-提现
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class ReportDailyPlanExt extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'report_daily_plan_ext';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'install_num' => 'integer', 'install_next_day' => 'integer', 'install_seven_day' => 'integer', 'install_request' => 'integer', 'install_fill' => 'integer', 'install_show' => 'integer', 'install_show_in' => 'integer', 'install_show_out' => 'integer', 'active_num' => 'integer', 'active_new' => 'integer', 'active_request' => 'integer', 'active_fill' => 'integer', 'active_show' => 'integer', 'activate_num' => 'integer', 'activate_next_day' => 'integer', 'activate_seven_day' => 'integer', 'activate_show' => 'integer', 'activate_show_in' => 'integer', 'activate_show_out' => 'integer', 'old_user_num' => 'integer', 'old_show' => 'integer', 'old_show_in' => 'integer', 'old_show_out' => 'integer', 'create_time' => 'integer', 'update_time' => 'integer'];
}