<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Constants\ErrorCode;
use App\Service\UserService;
use App\Utils\Log;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApiAuthMiddleware implements MiddlewareInterface
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
        if(!$this->checkAuth($request->getMethod(), $request->getUri()->getPath())){
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
     * 校验接口权限
     * @param string $method
     * @param string $path
     * @return bool
     */
    protected function checkAuth(string $method, string $path): bool
    {
        $method = strtolower($method);
        $userInfo = Context::get('_userInfo');
        if(data_get($userInfo, 'is_super') == 1) {
            return true;
        }
        $path = explode('/', trim($path, '/'));
        $method = strtolower($method);

        $apis = data_get($userInfo, 'apis');


        return $this->searchTree($path, $apis, $method);
    }

    protected function searchTree(array $path, array $tree, string $method): bool
    {
        if(count($path) <= 0){
            return true;
        }
        $node = array_shift($path);
        if(!isset($tree[$node])){
            if(!isset($tree['*'])){
                return false;
            }
            $node = '*';
        }
        if(count($path) <= 0){
            return isset($tree[$node]['method'][$method]);
        }
        return $this->searchTree($path, $tree[$node]['child'], $method);
    }
}
