<?php

declare (strict_types=1);

namespace App\ModelTools;

use Hyperf\Database\Model\Events\Creating;
use Hyperf\DbConnection\Model\Model;
use Hyperf\Snowflake\Concern\Snowflake;

/**
 * @property int $is_enable 是否开启:[0]否[1]是
 */
class PaidGoods extends Model
{
    use Snowflake {
        creating as create;
    }
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    protected $dateFormat = 'U';

    protected $primaryKey = 'goods_no';
    public $incrementing = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'app_paid_goods';
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'tools';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'goods_no', 'goods_name', 'goods_price', 'discounts_goods', 'is_discounts', 'goods_sku', 'is_enable',
        'created_time', 'updated_time',
    ];

    protected $casts = [
        'goods_sku' => 'array'
    ];

    public function creating(Creating $event)
    {
        $this->create();
    }
}