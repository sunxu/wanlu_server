<?php

declare(strict_types=1);

namespace App\Model;

class UserApp extends Model
{
    public $timestamps = false;

    protected $table = 'user_app_relation';

    protected $primaryKey = 'id';
}
