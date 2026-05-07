<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Constants\ErrorCode;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AppAuthMiddleware implements MiddlewareInterface
{
    protected $request;

    public function __construct(RequestInterface $request, HttpResponse $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if(is_local()){
            return $handler->handle($request);
        }
        if(!$this->checkAuth($this->request->route('appCode'))){
            $data = [
                'code' => ErrorCode::NONE_AUTH,
                'msg' => ErrorCode::getMessage(ErrorCode::NONE_AUTH),
                'data' => new \stdClass(),
            ];
            return $this->response->json($data)->withHeader('SERVER-TIMESTAMP', time());
        }
        return $handler->handle($request);
    }

    /**
     * 校验权限
     * @param string $method
     * @param string $appCode
     * @return bool
     */
    protected function checkAuth(string $appCode): bool
    {
        $userInfo = Context::get('_userInfo');
        if(data_get($userInfo, 'is_super') == 1) {
            return true;
        }
        $appCodes = collect(data_get($userInfo, 'apps'))->pluck('app_code')->all();

        return in_array($appCode, $appCodes);
    }
}
