<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Events\Creating;
use Hyperf\Database\Model\Events\Updating;

class UserRole extends Model
{
    public $timestamps = false;

    protected $table = 'user_role_relation';

    protected $primaryKey = 'id';

    protected $fillable = [
        ''
    ];

    public function creating(Creating $event)
    {
        $this->setAttribute('created_time', time());
        $this->setAttribute('updated_time', time());
    }

    public function updating(Updating $event)
    {
        $this->setAttribute('updated_time', time());
    }


}
