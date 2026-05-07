<?php

declare(strict_types=1);

namespace App\Utils;

use Hyperf\Guzzle\ClientFactory;

class Http
{
    /**
     * @var \Hyperf\Guzzle\ClientFactory
     */
    private $clientFactory;

    public function __construct(ClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    public function get(array $options = [])
    {
        // $options 等同于 GuzzleHttp\Client 构造函数的 $config 参数
        // $client 为协程化的 GuzzleHttp\Client 对象
        return $this->clientFactory->create($options);
    }
}