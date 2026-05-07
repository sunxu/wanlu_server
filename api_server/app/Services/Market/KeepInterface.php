<?php

namespace App\Services\Market;

interface KeepInterface
{
    public function doKeep(string $appCode, array $params): void;
}