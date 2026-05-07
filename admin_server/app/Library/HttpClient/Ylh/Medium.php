<?php

declare(strict_types=1);

namespace App\Library\HttpClient\Ylh;

class Medium extends BaseClient
{
    /**
     * 获取媒体
     * https://developers.adnet.qq.com/doc/reporting/index
     * @param array $params
     * @return mixed
     */
    public function list(array $params = [])
    {
        $params = array_merge([
            'page' => 1,
            'page_size' => 100
        ], $params);
        return $this->post('/open/v1.1/medium/list', $params);
    }
}