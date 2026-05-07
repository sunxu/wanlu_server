<?php

declare(strict_types=1);

namespace App\Library\HttpClient\Csjmedia;

class Code extends BaseClient
{
    public function create(array $params = [])
    {
        return $this->post('/union/media/open_api/code/create', $params);
    }
}