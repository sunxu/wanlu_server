<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Utils\Log;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AccessLogMiddleware implements MiddlewareInterface
{
    protected $request;

    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $trace = $this->request->getHeaderLine('X-TRICE');
        $trace = $trace?:create_uuid();
        $this->request->withHeader('X-TRICE', $trace);
        Context::set('x_trace', $trace);
        $requestTime = microtime(true);

        $response = $handler->handle($request)->withHeader('SERVER-TIMESTAMP', time());

        $rq_time = microtime(true) - $requestTime;


        $log = [
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'encrypt' => $request->getBody()->getContents(),
            'param' => $this->request->all(),
            'response' => json_decode($response->getBody()->getContents(), true),
            'req_header' => $request->getHeaders(),
            'resp_header' => $response->getHeaders(),
            'rq_time' => sprintf('%.2f', $rq_time),
        ];
        Log::access('info', 'access', $log);

        return $response;
    }
}
