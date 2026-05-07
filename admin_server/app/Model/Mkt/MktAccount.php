<?php

declare (strict_types=1);
namespace App\Model\Mkt;

use Hyperf\DbConnection\Model\Model;
/**
 * @property string $account_id 帐户ID
 * @property string $account_name 帐户名
 * @property string $account_role 帐户角色
 * @property int $is_valid 授权有效性[0]不可用[1]可用
 * @property string $user_id 授权用户ID
 * @property int $updated_time 更新时间
 * @property int $created_time 创建时间
 */
class MktAccount extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    public $incrementing = false;
    protected $dateFormat = 'U';
    protected $primaryKey = 'account_id';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mkt_account';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'account_id', 'account_name', 'account_role', 'is_valid', 'user_id'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['is_valid' => 'integer', 'updated_time' => 'integer', 'created_time' => 'integer'];

    public function user()
    {
        return $this->hasOne(MktUser::class, 'user_id', 'user_id');
    }
}