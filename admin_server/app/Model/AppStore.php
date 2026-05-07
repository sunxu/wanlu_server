<?php

declare (strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Events\Creating;
use Hyperf\Database\Model\Events\Saving;

/**
 * @property int $is_enable_notify
 * @property array $store_config_huawei
 * @property array $store_info_huawei
 * @property array $store_config_vivo
 * @property array $store_info_vivo
 * @property array $store_config_oppo
 * @property array $store_info_oppo
 * @property array $store_config_xiaomi
 * @property array $store_info_xiaomi
 */
class AppStore extends Model
{
    protected $table = 'app_store';

    protected $fillable = [];

    protected $primaryKey = 'app_id';

    public $timestamps = false;

    protected $casts = [
        'store_config_huawei' => 'array',
        'store_config_xiaomi' => 'array',
        'store_config_oppo' => 'array',
        'store_config_vivo' => 'array',
        'store_info_huawei' => 'array',
        'store_info_xiaomi' => 'array',
        'store_info_oppo' => 'array',
        'store_info_vivo' => 'array',
    ];

    public function creating(Creating $event)
    {
        $this->setAttribute('created_time', time());
    }

    public function saving(Saving $event)
    {
        $this->setAttribute('updated_time', time());
    }
}