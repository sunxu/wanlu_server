<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use Hyperf\DbConnection\Model\Model;

/**
 * @property string $plan_id 计划ID
 * @property string $advertiser_id 广告主ID
 * @property string $app_code 应用
 * @property string $plan_name 计划名称
 * @property int $put_status 投放状态 0-默认 1-投放中 2-暂停
 * @property string $create_time 计划创建时间
 * @property string $update_time 计划修改时间
 * @property string $budget_mode 预算模式
 * @property string $budget_amount 预算金额
 * @property string $schedule_start 投放开始
 * @property string $schedule_end 投放结束
 * @property string $schedule_time 投放时段 jl-48*7 ks-24*7
 * @property int $created_time 创建时间
 * @property int $updated_time 修改时间
 * @property int $deleted_time 删除时间
 */
class MktPlan extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    public $incrementing = false;
    protected $dateFormat = 'U';
    protected $primaryKey = 'plan_id';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mkt_plan';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'plan_id', 'advertiser_id', 'app_code', 'plan_name', 'put_status',
        'create_time', 'update_time', 'budget_mode', 'budget_amount', 'schedule_start',
        'schedule_end', 'schedule_time'
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['put_status' => 'integer', 'created_time' => 'integer', 'updated_time' => 'integer', 'deleted_time' => 'integer'];

    public function advertiser()
    {
        return $this->hasOne(MktAdvertiser::class, 'advertiser_id', 'advertiser_id');
    }
}