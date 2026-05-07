<?php

declare(strict_types=1);

namespace App\Library\HttpClient;

class HttpClient
{
    public static function connect(string $client, $module)
    {
        $clientNamespace = 'App\\Library\\HttpClient\\' .  ucfirst($client) . '\\' .ucfirst($module);
        if (! class_exists($clientNamespace)) {
            throw new \Exception("[$client] [{$module}] Client not exists.");
        }

        return new $clientNamespace();
    }
}