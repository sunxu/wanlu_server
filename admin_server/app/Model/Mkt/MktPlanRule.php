<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $rule_id 计划监控规则
 * @property string $app_code 应用
 * @property string $advertiser_id 广告主
 * @property string $plan_ids 广告计划
 * @property string $name 规则名称
 * @property string $cost 消耗设置
 * @property int $cycle_day 周期（天）
 * @property string $threshold 阀值设置
 * @property string $execute_type 执行类型
 * @property string $execute_value 执行参数
 * @property string $execute_result 执行回执
 * @property int $execute_times 执行次数
 * @property string $execute_date 执行频次日期
 * @property int $status 状态 1-上架 0-下架
 * @property string $start_date 开始日期
 * @property string $end_date 结束日期
 * @property int $created_time 创建时间
 * @property int $updated_time 修改时间
 * @property int $deleted_time 删除时间
 */
class MktPlanRule extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    protected $dateFormat = 'U';
    protected $primaryKey = 'rule_id';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mkt_plan_rule';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'app_code',
        'advertiser_id',
        'plan_ids',
        'name',
        'cost',
        'cycle_day',
        'threshold',
        'execute_type',
        'execute_value',
        'execute_result',
        'execute_times',
        'execute_date',
        'start_date',
        'end_date',
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'rule_id' => 'integer',
        'cycle_day' => 'integer',
        'execute_times' => 'integer',
        'status' => 'integer',
        'created_time' => 'integer',
        'updated_time' => 'integer',
        'deleted_time' => 'integer',
        'plan_ids' => 'array',
        'cost' => 'array',
        'threshold' => 'array',
        'execute_value' => 'array',
        'execute_result' => 'array',
        'execute_date' => 'array',
    ];

    public function advertiser()
    {
        return $this->hasOne(MktAdvertiser::class, 'advertiser_id', 'advertiser_id');
    }
}