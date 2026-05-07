<?php

declare (strict_types=1);

namespace App\Model;

/**
 * @property int $account_id 账户id
 * @property int $app_code 应用标识
 * @property string $app_id
 * @property string $platform
 * @property string $app_name
 * @property string $package_name
 * @property int $status
 */
class AppMediaApp extends Model
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
    protected $table = 'app_media_app';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'account_id', 'app_code', 'app_id', 'platform', 'app_name', 'package_name', 'created_time', 'updated_time', 'status'
    ];

    protected $primaryKey = 'id';
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [

    ];
}