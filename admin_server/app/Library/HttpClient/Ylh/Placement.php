<?php

declare(strict_types=1);

namespace App\Library\HttpClient\Ylh;

class Placement extends BaseClient
{
    /**
     * 创建广告位
     * https://developers.adnet.qq.com/doc/reporting/index
     * @param array $params
     * @return mixed
     */
    public function add(array $params = [])
    {
        return $this->post('/open/v1.1/placement/add', $params);
    }
}