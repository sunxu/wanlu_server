<?php

declare(strict_types=1);

namespace App\Library\HttpClient;

use App\Utils\Http;
use App\Utils\Log;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\Str;

abstract class ClientAbstract implements ClientInterface
{
    /**
     * @Inject
     * @var RequestInterface
     */
    protected $request;

    protected $defaultConfig = [
        'timeout' => 20
    ];

    protected $defaultHeaders = [
        'X-SERVER' => 'uc',
    ];

    public function getClient()
    {
        return make(Http::class)->get($this->getClientConfigs());
    }

    public function getClientConfigs(): array
    {
        $configs = config("httpClient.clients.{$this->getClientName()}");
        if(!$configs){
            throw new \Exception("[{$this->getClientName()}]未设置基础配置 <App\Library\HttpClient\ClientAbstract.php>");
        }
        $configs = array_merge($this->defaultConfig, $configs);
        if(!isset($configs['base_uri'])){
            throw new \Exception("[{$this->getClientName()}]未设置 URL <App\Library\HttpClient\ClientAbstract.php>");
        }

        return $configs;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        array_unshift($arguments, $name);

        return call_user_func_array([$this, 'request'], $arguments);
    }

    public function request(string $method, string $uri, array $params = [])
    {
        $uri = $this->buildUri($uri);
        $header = $this->buildHeader();
        $options = $this->buildOptions($params, $header, $method, $uri);
        $reqData = [
            'client' => $this->getClientName(),
            'host' => data_get($this->getClientConfigs(), 'base_uri'),
            'method' => $method,
            'uri' => $uri,
            'options' => $options,
            'header' => $header,
        ];
        $this->beforeRequest($reqData);
        $startTime = microtime(true);
        try {
            $httpResponse = $this->getClient()->request(strtoupper($method), $uri, $options);
        }catch (GuzzleException $e){
            $data = [
                'clientName' => $this->getClientName(),
                'uri' => $uri,
                'errmsg' => $e->getMessage(),
            ];
            Log::http('error', "请求服务异常{$this->getClientName()}", $data);
            throw new \Exception("调用【{$this->getClientName()}】失败", $e->getCode(), $e);
        }
        $responseContent = $httpResponse->getBody()->getContents();
        $this->afterRequest([
            'req' => $reqData,
            'uri' => $uri,
            'resp_body' => $responseContent,
            'resp_header' => $httpResponse->getHeaders(),
            'resp_time' => microtime(true) - $startTime,
        ]);

        return json_decode($responseContent, true);
    }

    protected function buildOptions(array $options, array $headers, string $method, string $uri)
    {
        $contentType = data_get($headers, 'Content-Type', 'application/json');
        $options = array_intersect(['json', 'body', 'form_params', 'multipart'], array_keys($options)) ? $options : ['json' => $options];
        if (data_get($options, 'json')) {
            if (! strpos($contentType, 'application/json') && ! strpos($contentType, 'application/x-json')) {
                data_set($headers, 'Content-Type', 'application/json');
            }
        }
        if (data_get($options, 'form_params')) {
            if (! strpos($contentType, 'application/x-www-form-urlencoded') && ! strpos($contentType, 'application/x-www-form-urlencoded')) {
                data_set($headers, 'Content-Type', 'application/x-www-form-urlencoded');
            }
        }
        if (data_get($options, 'multipart')) {
            $data = data_get($options, 'multipart');
            $multipart = [];
            foreach ($data as $k => $v) {
                $multipart[] = [
                    'name' => $k,
                    'contents' => $v
                ];
            }
            $options['multipart'] = $multipart;
            data_set($headers, 'Content-Type', 'multipart/form-data');
        }

        $options['headers'] = array_merge(data_get($options, 'headers', []), $headers);

        return $options;
    }

    protected function buildUri(string $uri): string
    {
        return Str::startsWith($uri, '/')?$uri:'/'.$uri;
    }

    protected function buildHeader(): array
    {
        return array_merge([
            'X-TRICE' => Context::get('x_trace', create_uuid()),
            'Content-Type' => 'application/json'
        ], $this->defaultHeaders);
    }

    protected function beforeRequest(array $data)
    {
        data_set($data, 'flag', 'http start');
        Log::http('info', '请求开始', $data);
    }

    protected function afterRequest(array $data)
    {
        data_set($data, 'flag', 'http end');
        Log::http('info', '请求结束', $data);
    }
}