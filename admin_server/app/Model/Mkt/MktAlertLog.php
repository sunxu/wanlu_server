<?php

declare(strict_types=1);

namespace App\Model\Mkt;

use App\Model\Model;
use Hyperf\Database\Model\Events\Creating;
use Hyperf\Database\Model\Events\Updating;
use Hyperf\Snowflake\Concern\Snowflake;

/**
 * @property int $log_id 日志ID
 * @property int $rule_id 规则ID
 * @property string $item_type 类型 advertiser/project
 * @property string $item_id 广告主ID/项目ID
 * @property string $item_name 名称
 * @property array $trigger_data 触发时的数据
 * @property int $status 当前状态 0=正常 1=已触发
 * @property int $notified_time 通知时间
 * @property int $created_time 创建时间
 * @property int $updated_time 更新时间
 */
class MktAlertLog extends Model
{
    use Snowflake {
        creating as snowflakeCreate;
    }

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = false;
    protected $dateFormat = 'U';
    protected $primaryKey = 'log_id';

    protected $table = 'mkt_alert_log';

    protected $fillable = [
        'log_id', 'rule_id', 'item_type', 'item_id',
        'item_name', 'trigger_data', 'status',
        'notified_time', 'created_time', 'updated_time'
    ];

    protected $casts = [
        'rule_id' => 'integer',
        'trigger_data' => 'array',
        'status' => 'integer',
        'notified_time' => 'integer',
    ];

    public function creating(Creating $event)
    {
        $this->snowflakeCreate();
        $this->setAttribute('status', $this->status ?? 0);
        $this->setAttribute('created_time', time());
        $this->setAttribute('updated_time', time());
    }

    public function updating(Updating $event)
    {
        $this->setAttribute('updated_time', time());
    }
}
