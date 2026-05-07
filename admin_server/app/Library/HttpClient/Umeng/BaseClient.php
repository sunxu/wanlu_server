<?php

declare(strict_types=1);

namespace App\Library\HttpClient\Umeng;

use App\Library\HttpClient\ClientAbstract;
use Hyperf\Utils\Str;

abstract class BaseClient extends ClientAbstract
{
    public function getClientName(): string
    {
        return 'umeng';
    }

    protected function buildOptions(array $options, array $headers, string $method, string $uri)
    {
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $uri = str_replace('/openapi/', '', $uri);
        $options['_aop_signature'] = $this->signature($uri, $options);


        return [
            'headers' => $headers,
            'form_params' => $options
        ];
    }

    protected function buildUri(string $uri): string
    {
        $uri = Str::startsWith($uri, '/')?$uri:'/'.$uri;
        $uri = Str::endsWith($uri, '/') ? $uri : $uri . '/';
        $apiKey = config("umeng.apiKey");

        return "/openapi{$uri}{$apiKey}";
    }

    protected function signature(string $path, array $parameters) {
        $apiSecurity = config("umeng.apiSecurity");
        $paramsToSign = array ();
        foreach ( $parameters as $k => $v ) {
            $paramToSign = $k . $v;
            Array_push ( $paramsToSign, $paramToSign );
        }
        sort ( $paramsToSign );
        $implodeParams = implode ( $paramsToSign );
        $pathAndParams = $path . $implodeParams;
        $sign = hash_hmac ( "sha1", $pathAndParams, $apiSecurity, true );
        $signHexWithLowcase = bin2hex ( $sign );
        $signHexUppercase = strtoupper ( $signHexWithLowcase );
        return $signHexUppercase;
    }
}