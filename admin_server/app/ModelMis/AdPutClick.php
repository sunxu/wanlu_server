<?php

declare (strict_types=1);
namespace App\ModelMis;

use App\Utils\SuffixModel;
/**
 * @property int $user_id 用户ID
 * @property int $log_id 投放点击日志ID
 * @property string $source 投放来源 jl ks
 * @property string $imei_md5 IMEI的MD5
 * @property string $oaid OAID
 * @property string $oaid_md5 OAID的MD5
 * @property string $android_id_md5 ANDROIDID的MD5
 * @property string $ip IP
 * @property string $ua 用户代理
 * @property string $model 手机型号
 * @property string $ip_ua_md5 IP+UA的MD5
 * @property string $mac_md5 MAC的MD5
 * @property string $mac_upper_md5 MAC转大写的MD5
 * @property int $ts 点击广告毫秒时间戳
 * @property int $os 操作系统平台 [0]安卓[1]IOS[3]其他
 * @property string $advertiser_id 广告主ID
 * @property string $campaign_id 广告组ID
 * @property string $campaign_name 广告组名称
 * @property string $plan_id 广告计划ID
 * @property string $plan_name 广告计划名称
 * @property string $creative_id 广告创意ID
 * @property string $creative_name 广告创意名称
 * @property string $csite 广告投放场景
 * @property string $convert_id 转化ID
 * @property string $request_id 请求ID
 * @property string $union_site 对外广告位编码
 * @property string $callback_url 回调地址
 * @property string $hit_field 命中字段
 * @property int $install_time 设备安装时间
 * @property int $click_time 广告点击时间
 * @property int $activate_time 激活时间
 * @property int $activate_type 激活定义[1]开屏[2]归因[3]回收
 * @property int $activate_status 激活通知状态 [0]默认[1]激活待通知[2]激活已通知[3]扣量不通知
 * @property int $keep_time 次留时间
 * @property int $keep_status 次留通知状态 [0]默认[1]次留待通知[2]次留已通知[3]扣量不通知
 * @property int $action_time 关键行为时间
 * @property int $action_type 关键行为定义[0]不触发[1]回收率%[2]回收金额
 * @property int $action_status 关键行为通知状态 [0]默认[1]待通知[2]已通知[3]扣量不通知
 * @property int $keep24_time 24小时留存时间
 * @property int $keep24_status 24小时留存通知状态 [0]默认[1]待通知[2]已通知[3]扣量不通知
 * @property string $revenue 累计收益（元）
 * @property int $updated_time 更新时间
 * @property int $created_time 创建时间 归因时间
 */
class AdPutClick extends SuffixModel
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    protected $dateFormat = 'U';
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ad_put_click_{suffix}';
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
    protected $casts = ['user_id' => 'integer', 'log_id' => 'integer', 'ts' => 'integer', 'os' => 'integer', 'install_time' => 'integer', 'click_time' => 'integer', 'activate_time' => 'integer', 'activate_type' => 'integer', 'activate_status' => 'integer', 'keep_time' => 'integer', 'keep_status' => 'integer', 'action_time' => 'integer', 'action_type' => 'integer', 'action_status' => 'integer', 'keep24_time' => 'integer', 'keep24_status' => 'integer', 'updated_time' => 'integer', 'created_time' => 'integer'];
}