<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 
 * @property string $date 
 * @property string $app_code 
 * @property int $launch_type 
 * @property string $vendor_type
 * @property int $install 
 * @property int $show 
 * @property int $keep 
 * @property int $updated_time 
 * @property int $created_time 
 */
class AppDailyShowKeep extends Model
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
    protected $table = 'app_daily_show_keep';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date',
        'app_code',
        'launch_type',
        'vendor_type',
        'install',
        'show',
        'keep'
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'launch_type' => 'integer', 'install' => 'integer', 'show' => 'integer', 'keep' => 'integer', 'updated_time' => 'integer', 'created_time' => 'integer'];
}