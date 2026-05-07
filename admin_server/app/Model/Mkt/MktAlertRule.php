<?php

declare(strict_types=1);

namespace App\Model\Mkt;

use App\Model\Model;
use Hyperf\Database\Model\Events\Creating;
use Hyperf\Database\Model\Events\Updating;
use Hyperf\Snowflake\Concern\Snowflake;

/**
 * @property int $rule_id 规则ID
 * @property string $rule_name 规则名称
 * @property string $rule_type 规则类型 advertiser/project
 * @property array $app_code 应用代码
 * @property int $rule_enable 规则状态 0=关闭 1=开启
 * @property array $threshold 阈值配置（默认，兼容旧版）
 * @property array $time_points 时间点配置 [{'time':'00:00','threshold':{...}},...]
 * @property int $last_trigger_time 上次触发时间
 * @property int $created_time 创建时间
 * @property int $updated_time 更新时间
 * @property int $deleted_time 删除时间
 */
class MktAlertRule extends Model
{
    use Snowflake {
        creating as snowflakeCreate;
    }

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = false;
    protected $dateFormat = 'U';
    protected $primaryKey = 'rule_id';

    protected $table = 'mkt_alert_rule';

    protected $fillable = [
        'rule_id', 'rule_name', 'rule_type', 'app_code',
        'rule_enable', 'threshold', 'time_points', 'last_trigger_time',
        'created_time', 'updated_time', 'deleted_time'
    ];

    protected $casts = [
        'app_code' => 'array',
        'threshold' => 'array',
        'time_points' => 'array',
        'rule_enable' => 'integer',
        'last_trigger_time' => 'integer',
    ];

    public function creating(Creating $event)
    {
        $this->snowflakeCreate();
        $this->setAttribute('created_time', time());
        $this->setAttribute('updated_time', time());
        $this->setAttribute('rule_enable', $this->rule_enable ?? 0);
        $this->setAttribute('deleted_time', 0);
    }

    public function updating(Updating $event)
    {
        $this->setAttribute('updated_time', time());
    }

    public function delegates()
    {
        return $this->belongsToMany(
            MktAlertDelegate::class,
            'mkt_alert_rule_delegate',
            'rule_id',
            'delegate_id'
        );
    }
}
