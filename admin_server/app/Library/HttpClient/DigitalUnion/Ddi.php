<?php

declare(strict_types=1);

namespace App\Library\HttpClient\DigitalUnion;

class Ddi extends BaseClient
{
    /**
     * 请求数盟接口
     *
     * @param array $params
     * @return mixed
     */
    public function detection(array $params): array
    {
        $params = array_merge([
            'protocol' => 2
        ], $params);
        return $this->get('/q?'. http_build_query($params));
    }
}