<?php

declare(strict_types=1);

namespace App\Model;

use App\Constants\UserConst;
use Hyperf\Database\Model\Events\Creating;
use Hyperf\Database\Model\Events\Updating;
use Hyperf\Snowflake\Concern\Snowflake;

/**
 * @property int $user_id 用户 ID
 * @property string $user_name 用户名称
 * @property string $password 密码
 * @property string $salt 盐
 * @property int $is_super 超管
 * @property int $status 状态 [0]关闭 [1]正常
 */
class Account extends Model
{
    use Snowflake {
        creating as create;
    }

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'user_account';

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'user_id', 'user_name', 'password', 'salt', 'avatar', 'login_time', 'created_time', 'updated_time', 'status'
    ];

    public function roles()
    {
        return $this->hasManyThrough(Role::class, UserRole::class, 'user_id', 'role_id', 'user_id', 'role_id');
    }

    public function apps()
    {
        return $this->hasManyThrough(App::class, UserApp::class, 'user_id', 'app_id', 'user_id', 'app_id');
    }

    public function creating(Creating $event)
    {
        $this->create();
        $this->setAttribute('created_time', time());
        $this->setAttribute('updated_time', time());
    }

    public function updating(Updating $event)
    {
        $this->setAttribute('updated_time', time());
    }
}
