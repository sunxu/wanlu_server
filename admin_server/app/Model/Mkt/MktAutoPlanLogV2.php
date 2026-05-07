<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use App\Model\Model;
use Hyperf\Database\Model\Events\Creating;
use Hyperf\Snowflake\Concern\Snowflake;

/**
 * @property int $log_id 日志id
 * @property int $plan_id 计划id
 * @property int $begin_time 开始时间
 * @property int $end_time 结束时间
 * @property int $schedule_log 日志内容
 * @property int $plan_snapshoot 计划快照
 * @property int $created_time 创建时间
 */
class MktAutoPlanLogV2 extends Model
{
    use Snowflake {
        creating as create;
    }

    const CREATED_AT = 'created_time';

    public $timestamps = false;
    protected $dateFormat = 'U';

    protected $table = 'mkt_auto_plan_log_v2';

    protected $primaryKey = 'log_id';

    protected $fillable = [
        'log_id', 'plan_id', 'begin_time', 'end_time', 'schedule_log', 'plan_snapshoot', 'created_time'
    ];

    protected $casts = [
        'schedule_log' => 'array',
        'plan_snapshoot' => 'array',
    ];

    public function creating(Creating $event)
    {
        $this->create();
        $this->setAttribute('created_time', time());
    }
}