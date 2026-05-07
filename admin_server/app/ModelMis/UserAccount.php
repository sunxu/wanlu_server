<?php

declare (strict_types=1);
namespace App\ModelMis;

use App\Utils\SuffixModel;
/**
 * @property int $user_id 用户id
 * @property string $nick_name 昵称
 * @property string $openid 微信openid
 * @property string $portrait 头像
 * @property int $grade 用户等级
 * @property int $grade_score 用户等级分
 * @property string $gold_coin 金币
 * @property string $gold_ingot 元宝
 * @property string $withdrawal_amount 已提现金额 单位分
 * @property int $channel_id 下载渠道
 * @property int $login_day 连续登录天
 * @property int $login_count 累计登录天
 * @property int $sign_count 累计打卡天数
 * @property int $withdrawal_count 累计提现次数
 * @property int $last_sign 上次打卡时间
 * @property int $last_withdrawal 上次提现时间
 * @property int $last_login 上次登录时间
 * @property int $finish_new_task 是否完成新手任务:0=未完成，1=已完成
 * @property int $finish_time 完成新手任务时间
 * @property int $status 禁用状态：1=可用，0=禁用
 * @property int $is_closed 注销状态：1=可用，0=注销
 * @property int $created_time 创建时间
 * @property int $updated_time 修改时间
 * @property string $app_version 注册app版本
 * @property string $withdrawal_amount_success 已提现成功金额
 * @property int $withdrawal_count_success 累计提现成功次数
 * @property string $advertiser_id 广告主ID
 * @property string $plan_id 广告计划ID
 * @property string $source 来源 ks-快手 jl-巨量
 * @property int $launch_type 投放类型[0]双出[1]关键行为
 * @property string $vendor_type 生产厂商
 */
class UserAccount extends SuffixModel
{
    public $timestamps = false;
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_{suffix}_account';
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mis';
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
    protected $casts = ['user_id' => 'integer', 'grade' => 'integer', 'grade_score' => 'integer', 'channel_id' => 'integer', 'login_day' => 'integer', 'login_count' => 'integer', 'sign_count' => 'integer', 'withdrawal_count' => 'integer', 'last_sign' => 'integer', 'last_withdrawal' => 'integer', 'last_login' => 'integer', 'finish_new_task' => 'integer', 'finish_time' => 'integer', 'status' => 'integer', 'is_closed' => 'integer', 'created_time' => 'integer', 'updated_time' => 'integer', 'withdrawal_count_success' => 'integer', 'launch_type' => 'integer'];
}