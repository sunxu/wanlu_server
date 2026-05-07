<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use Hyperf\DbConnection\Model\Model;

/**
 * @property string $advertiser_id 广告主ID
 * @property string $advertiser_name 广告主名称
 * @property string $agency_name 代理名称
 * @property string $platform 投放平台
 * @property string $account_id 帐户ID
 * @property string $app_code 所属应用
 * @property int $status 状态 1-正常 0-禁用
 * @property int $launch_type 投放方式0-次留双出 1-关键行为
 * @property int $active_type 激活类型[1]开屏[2]归因[3]回收[4]自定义
 * @property string $active_value 激活定义设置
 * @property int $action_type 关键行为类型[0]不触发[1]回收率[2]回收金额[3]自定义
 * @property string $action_value 关键行为定义
 * @property string $action_revenue 关键行为:回收率、回收金额
 * @property int $updated_time 更新时间
 * @property int $created_time 创建时间
 * @property int $deleted_time 删除时间 0-未删除
 * @property string $extra 额外配置
 * @property int $notify_discount 扣量百分比
 * @property int $keep_type 留存定义[0]自然日[1]24小时
 * @property int $notify_fill 激活补充[0]否[1]是-留存满足
 */
class MktAdvertiser extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    public $incrementing = false;
    protected $dateFormat = 'U';
    protected $primaryKey = 'advertiser_id';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mkt_advertiser';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'advertiser_id', 'advertiser_name', 'platform', 'account_id', 'app_code',
        'launch_type', 'active_type', 'active_value', 'action_type', 'action_value',
        'action_revenue', 'status', 'agency_name', 'extra', 'notify_discount',
        'alert_status', 'keep_type'
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['status' => 'integer', 'launch_type' => 'integer', 'active_type' => 'integer', 'action_type' => 'integer', 'updated_time' => 'integer', 'created_time' => 'integer', 'deleted_time' => 'integer', 'extra' => 'array', 'alert_status' => 'integer'];

    public function account()
    {
        return $this->hasOne(MktAccount::class, 'account_id', 'account_id');
    }

    public function project()
    {
        return $this->hasMany(MktProject::class, 'advertiser_id', 'advertiser_id');
    }

    public function plan()
    {
        return $this->hasMany(MktPlan::class, 'advertiser_id', 'advertiser_id');
    }
}