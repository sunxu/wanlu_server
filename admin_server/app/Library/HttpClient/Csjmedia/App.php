<?php

declare(strict_types=1);

namespace App\Library\HttpClient\Csjmedia;

class App extends BaseClient
{
    public function list(array $params = [])
    {
        $params = array_merge([
            'page' => 1,
            'page_size' => 500,
        ], $params);
        return $this->post('/union/media/open_api/site/query', $params);
    }
}