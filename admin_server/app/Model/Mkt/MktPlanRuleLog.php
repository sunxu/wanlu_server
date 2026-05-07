<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id 计划监控执行记录
 * @property string $plan_id 计划ID
 * @property int $rule_id 规则ID
 * @property int $rule_times 规则的第几个周期
 * @property string $hit_threshold 命中的阀值
 * @property string $rule_data 命中规则数据留存
 * @property int $created_time 创建时间
 * @property int $updated_time 更新时间
 */
class MktPlanRuleLog extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    protected $dateFormat = 'U';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mkt_plan_rule_log';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'rule_id',
        'plan_id',
        'rule_times',
        'hit_threshold',
        'rule_data',
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'rule_id' => 'integer', 'rule_times' => 'integer', 'created_time' => 'integer', 'updated_time' => 'integer', 'hit_threshold' => 'array', 'rule_data' => 'array'];
}