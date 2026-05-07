<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Constants\ErrorCode;
use App\Model\App;
use App\Utils\RedisManager;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SignMiddleware implements MiddlewareInterface
{
    protected const SKIP_TIME = [
//        '/test' => '/test',
    ];

    protected $request;

    protected $response;

    public function __construct(RequestInterface $request, HttpResponse $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);

        if(is_local()){
            return $handler->handle($request);
        }
        $path = $this->request->getPathInfo();
        $expireTime = 60 * 5;
        $headerSign = $this->request->getHeaderLine('X-SIGN');
        //签名是否过期
        $timestamp = $this->request->getHeaderLine('X-TIMESTAMP');
        if(!isset(self::SKIP_TIME[$path]) && abs(time() - intval($timestamp)) > $expireTime){
            $data = [
                'code' => ErrorCode::SIGN_EXPIRE,
                'msg' => ErrorCode::getMessage(ErrorCode::SIGN_EXPIRE),
                'data' => new \stdClass(),
            ];
            return $this->response->json($data)->withHeader('SERVER-TIMESTAMP', time());
        }
        //签名是否重复
        $redis = RedisManager::get();
        $isUse = $redis->setNx("sign:{$headerSign}", 'sign');
        if(!$isUse){
            $data = [
                'code' => ErrorCode::SIGN_REPETITION,
                'msg' => ErrorCode::getMessage(ErrorCode::SIGN_REPETITION),
                'data' => new \stdClass(),
            ];
            return $this->response->json($data)->withHeader('SERVER-TIMESTAMP', time());
        }
        $redis->expire("sign:{$headerSign}", $expireTime + 10);
        //校验签名
        $str1 = $this->getStr1();
        $str2 = $this->getStr2();
        $str4 = md5($str1.$str2);
        $sign = substr($str4, 5, 10);
        if($sign != $headerSign){
            $data = [
                'code' => ErrorCode::SIGN_ERROR,
                'msg' => ErrorCode::getMessage(ErrorCode::SIGN_ERROR),
                'data' => new \stdClass(),
            ];
            return $this->response->json($data)->withHeader('SERVER-TIMESTAMP', time());
        }

        return $handler->handle($request);
    }

    public function getStr1(): string
    {
        $headers = ['X-ABBR', 'X-APPCODE', 'X-APPID', 'X-NONCESTR', 'X-RUN', 'X-TIMESTAMP', 'X-TRICE'];
        $str1 = '';
        foreach ($headers as $header){
            if(!$headerValue = $this->request->getHeaderLine($header)){
                throw new \Exception(ErrorCode::getMessage(ErrorCode::PARAMS_ERROR), ErrorCode::PARAMS_ERROR);
            }
            if($header == 'X-NONCESTR'){
                $headerValue = substr($headerValue, 0, 8);
            }
            $str1 .= "{$header}={$headerValue}&";
        }

        return strtolower(rtrim($str1, '&'));
    }

    public function getStr2(): string
    {
        $appId = $this->request->getHeaderLine('x-appid');
        $appInfo = (new App())->getAppInfo(intval($appId));
        if(
            data_get($appInfo, 'app_code') != $this->request->getHeaderLine('X-APPCODE') ||
            data_get($appInfo, 'abbr') !=$this->request->getHeaderLine('X-ABBR')
        ){
            throw new \Exception(ErrorCode::getMessage(ErrorCode::PARAMS_ERROR), ErrorCode::PARAMS_ERROR);
        }
        return data_get($appInfo, 'sign_secret_key');
    }
}
