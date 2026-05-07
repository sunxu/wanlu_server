<?php

declare(strict_types=1);

namespace App\Library\HttpClient\DigitalUnion;

use App\Library\HttpClient\ClientAbstract;

abstract class BaseClient extends ClientAbstract
{
    public function getClientName(): string
    {
        return 'digital_union';
    }
}