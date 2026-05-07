<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use Hyperf\DbConnection\Model\Model;

/**
 * @property string $user_id 用户ID
 * @property string $user_name 用户名
 * @property string $user_email 用户邮箱
 * @property string $app_id 应用ID
 * @property string $access_token 请求Token
 * @property int $access_time Token过期时间
 * @property string $refresh_token 刷新Token
 * @property int $refresh_time 刷新过期时间
 * @property int $updated_time 更新时间
 * @property int $created_time 创建时间
 */
class MktUser extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    public $incrementing = false;
    protected $dateFormat = 'U';
    protected $primaryKey = 'user_id';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mkt_user';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'user_name', 'user_email', 'app_id', 'access_token', 'access_time', 'refresh_token', 'refresh_time'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['access_time' => 'integer', 'refresh_time' => 'integer', 'updated_time' => 'integer', 'created_time' => 'integer'];

    public function app()
    {
        return $this->hasOne(MktApp::class, 'app_id', 'app_id');
    }
}