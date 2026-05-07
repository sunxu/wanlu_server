<?php

declare (strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $app_code
 * @property string $channel
 * @property string $key
 * @property string $vendor
 * @property string $source
 * @property string $launch_type
 * @property string $info
 * @property string $placement
 * @property int $updated_time
 * @property int $created_time
 * @property int $deleted_time
 */
class AdStrategy extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    protected $dateFormat = 'U';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ad_strategy';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'app_code',
        'channel',
        'key',
        'vendor',
        'source',
        'launch_type',
        'info',
        'placement',
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'updated_time' => 'integer', 'created_time' => 'integer', 'deleted_time' => 'integer'];

    public $adType = ['native', 'splash', 'inter', 'full_video'];
}