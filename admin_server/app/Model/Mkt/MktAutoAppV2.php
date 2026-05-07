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
 * @property array $threshold
 * @property string $execute_operate
 * @property int $created_time
 * @property int $updated_time
 */
class MktAutoAppV2 extends Model
{
    use Snowflake {
        creating as create;
    }

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = false;
    protected $dateFormat = 'U';

    protected $table = 'mkt_auto_app_v2';

    protected $primaryKey = 'plan_id';

    protected $fillable = [
        'plan_id', 'plan_name','plan_enable', 'app_code', 'threshold', 'execute_operate', 'created_time', 'updated_time'
    ];

    protected $casts = [
        'app_code' => 'array',
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