<?php

declare(strict_types=1);

namespace App\Library\HttpClient;

interface ClientInterface
{
    /**
     * @return array
     */
    public function getClientConfigs():array;

    /**
     * @return mixed
     */
    public function getClient();

    /**
     * @param string $method
     * @param string $uri
     * @param array $options
     * @return mixed
     */
    public function request(string $method, string $uri, array $options = []);

    /**
     * @return string
     */
    public function getClientName():string;
}