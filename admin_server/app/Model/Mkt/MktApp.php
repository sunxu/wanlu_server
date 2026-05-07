<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use Hyperf\DbConnection\Model\Model;

/**
 * @property string $app_id 应用ID
 * @property string $app_name 应用名
 * @property string $app_secret 应用密钥
 * @property string $platform 开放平台
 * @property string $auth_url 授权地址
 * @property string $login_account 登陆账号
 * @property int $updated_time 更新时间
 * @property int $created_time 创建时间
 */
class MktApp extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    public $incrementing = false;
    protected $dateFormat = 'U';
    protected $primaryKey = 'app_id';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mkt_app';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['updated_time' => 'integer', 'created_time' => 'integer'];
}