<?php

declare(strict_types=1);

namespace App\Library\HttpClient\Ks;

class App extends BaseClient
{
    public function list(array $params = [])
    {
        $params = array_merge([
            'page' => 1,
            'pageSize' => 500
        ], $params);
        return $this->post('/api/app/get', $params);
    }
}