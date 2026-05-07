<?php

declare(strict_types=1);

namespace App\Model\Mkt;

use App\Model\Model;
use Hyperf\Database\Model\Events\Creating;
use Hyperf\Snowflake\Concern\Snowflake;

/**
 * @property int $id 关联ID
 * @property int $rule_id 规则ID
 * @property int $delegate_id 代投配置ID
 */
class MktAlertRuleDelegate extends Model
{
    use Snowflake {
        creating as snowflakeCreate;
    }

    public $timestamps = false;
    protected $dateFormat = 'U';
    protected $primaryKey = 'id';

    protected $table = 'mkt_alert_rule_delegate';

    protected $fillable = [
        'id', 'rule_id', 'delegate_id'
    ];

    protected $casts = [
        'rule_id' => 'integer',
        'delegate_id' => 'integer',
    ];

    public function creating(Creating $event)
    {
        $this->snowflakeCreate();
    }
}
