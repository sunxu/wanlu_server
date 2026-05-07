<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\UserService;
use App\Utils\Log;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LoginMiddleware implements MiddlewareInterface
{
    protected $request;

    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->request->getHeaderLine('X-TOKEN');
        $userInfo = make(UserService::class)->getUserByToken($token);
        Context::set('_userInfo', $userInfo);

        return $handler->handle($request);
    }
}
