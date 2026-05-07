<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use App\Model\Model;
use Hyperf\Database\Model\Events\Creating;
use Hyperf\Database\Model\Events\Updating;
use Hyperf\Snowflake\Concern\Snowflake;

/**
 * @property int $plan_id 计划id
 * @property string $plan_name
 * @property array $advertisers 广告主ID
 * @property int $plan_enable
 * @property string $app_code
 * @property int $plan_status
 * @property int $last_start_time
 * @property int $last_end_time
 * @property array $threshold
 * @property string $execute_operate
 * @property int $created_time
 * @property int $updated_time
 * @property int $last_execute_result
 */
class MktAutoPlanV2 extends Model
{
    use Snowflake {
        creating as create;
    }

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = false;
    protected $dateFormat = 'U';

    protected $table = 'mkt_auto_plan_v2';

    protected $primaryKey = 'plan_id';

    protected $fillable = [
        'plan_id', 'plan_name', 'advertisers', 'plan_enable', 'app_code', 'plan_status', 'last_start_time', 'last_end_time',
        'threshold', 'execute_operate', 'created_time', 'updated_time', 'last_execute_result'
    ];

    protected $casts = [
        'advertisers' => 'array',
        'threshold' => 'array',
    ];

    public function creating(Creating $event)
    {
        $this->create();
        $this->setAttribute('created_time', time());
        $this->setAttribute('updated_time', time());
    }

    public function updating(Updating $event)
    {
        $this->setAttribute('updated_time', time());
    }
}