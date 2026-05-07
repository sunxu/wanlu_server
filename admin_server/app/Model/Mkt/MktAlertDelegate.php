<?php

declare(strict_types=1);

namespace App\Model\Mkt;

use App\Model\Model;
use Hyperf\Database\Model\Events\Creating;
use Hyperf\Database\Model\Events\Updating;
use Hyperf\Snowflake\Concern\Snowflake;

/**
 * @property int $delegate_id 配置ID
 * @property string $delegate_name 代投名称
 * @property string $ding_token 钉钉token
 * @property string $ding_secret 钉钉secret
 * @property int $status 状态 0=禁用 1=启用
 * @property int $created_time 创建时间
 * @property int $updated_time 更新时间
 * @property int $deleted_time 删除时间
 */
class MktAlertDelegate extends Model
{
    use Snowflake {
        creating as snowflakeCreate;
    }

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = false;
    protected $dateFormat = 'U';
    protected $primaryKey = 'delegate_id';

    protected $table = 'mkt_alert_delegate';

    protected $fillable = [
        'delegate_id', 'delegate_name', 'ding_token',
        'ding_secret', 'status', 'created_time',
        'updated_time', 'deleted_time'
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function creating(Creating $event)
    {
        $this->snowflakeCreate();
        $this->setAttribute('created_time', time());
        $this->setAttribute('updated_time', time());
        $this->setAttribute('status', $this->status ?? 1);
        $this->setAttribute('deleted_time', 0);
    }

    public function updating(Updating $event)
    {
        $this->setAttribute('updated_time', time());
    }
}
