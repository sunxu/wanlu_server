<?php

declare(strict_types=1);

namespace App\Library\HttpClient\Ks;

class Position extends BaseClient
{
    public function add(array $params = [])
    {
        return $this->post('/api/position/add', $params);
    }

    public function modCpmFloor(array $params = [])
    {
        return $this->post('/api/position/modCpmFloor', $params);
    }
}