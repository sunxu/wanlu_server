<?php

namespace App\Lib\Amap;

use App\Services\Helper\Helper;

class Amapi
{
    protected $requestUrl;

    protected $key;

    public function __construct()
    {
        $config = config('amap');

        $this->requestUrl = $config['request_url'] ?? '';
        $this->key = $config['key'] ?? '';
    }

    public function ip(string $ip)
    {
        $url = $this->requestUrl . '/v3/ip';
        $param = [
            'key' => $this->key,
            'ip' => $ip,
        ];

        $result = Helper::request($url, 'GET', $param);
        $result = json_decode($result['result'], true);

        $data = [];
        if (($result['status'] ?? 0) == 1 && !empty($result['city'])) {
            $data = [
                'province' => $result['province'],
                'city' => $result['city'],
                'city_code' => $result['adcode'],
            ];
        }

        return $data;
    }
}