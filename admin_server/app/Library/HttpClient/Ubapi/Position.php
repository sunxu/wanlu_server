<?php

declare(strict_types=1);

namespace App\Library\HttpClient\Ubapi;

class Position extends BaseClient
{
    public function add(array $params = [])
    {
        return $this->post('/ssp/1/sspservice/appadpos/app/adpos/create', $params);
    }
}