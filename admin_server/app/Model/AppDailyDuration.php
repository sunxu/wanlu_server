<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 
 * @property string $date 
 * @property string $app_code 
 * @property string $app_version 
 * @property int $is_new_app_version
 * @property string $vendor_type
 * @property string $os_version_type 
 * @property int $num 
 * @property string $duration 
 * @property int $updated_time 
 * @property int $created_time 
 */
class AppDailyDuration extends Model
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
    protected $table = 'app_daily_duration';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date',
        'app_code',
        'app_version',
        'is_new_app_version',
        'vendor_type',
        'os_version_type',
        'num',
        'duration'
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'num' => 'integer', 'is_new_app_version' => 'integer', 'updated_time' => 'integer', 'created_time' => 'integer'];
}