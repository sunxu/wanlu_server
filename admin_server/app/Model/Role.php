<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Events\Creating;
use Hyperf\Database\Model\Events\Updating;

class Role extends Model
{
    public $timestamps = false;

    protected $table = 'user_role';

    protected $primaryKey = 'role_id';

    protected $fillable = [
        'role_name', 'pages', 'apis', 'created_time', 'updated_time'
    ];

    protected $casts = [
        'pages' => 'array',
        'apis' => 'array',
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
