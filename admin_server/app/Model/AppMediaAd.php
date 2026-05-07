<?php

declare (strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Events\Creating;

/**
 * @property int $account_id 账户id
 * @property string $gmid
 * @property string $media_app_id
 * @property string $ad_type
 * @property string $ad_type_key
 * @property float $price
 * @property string $ad_name
 * @property string $platform
 * @property string $ad_id
 * @property int $is_bind
 * @property int $is_create
 * @property string $exec_key
 * @property string $remark
 * @property int $is_bidding
 */
class AppMediaAd extends Model
{
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'app_media_ad';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'created_time', 'gmid', 'media_app_id','ad_type', 'price', 'ad_name', 'platform', 'ad_id', 'is_bind', 'is_create',
        'exec_key', 'remark', 'is_bidding', 'ad_type_key'
    ];

    protected $primaryKey = 'id';
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [

    ];

    public function creating(Creating $event)
    {
        $this->setAttribute('created_time', time());
    }
}