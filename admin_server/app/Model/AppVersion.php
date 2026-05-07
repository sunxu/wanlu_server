<?php

declare(strict_types=1);

namespace App\Model;
use Hyperf\DbConnection\Model\Model;

class AppVersion extends Model
{
    protected $connection = 'nls';
    protected $table = 'app_version';
}
