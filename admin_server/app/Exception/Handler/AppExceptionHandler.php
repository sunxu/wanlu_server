<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Utils\Log;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
{
    protected $request;

    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {

        $code = $throwable->getCode() ?: ErrorCode::SERVER_ERROR;
        $msg = $throwable instanceof BusinessException ? $throwable->getMessage() : '服务内部异常';
        $data = ['throwable' => ['code' => $throwable->getCode(), 'message' => $throwable->getMessage(), 'file' => $throwable->getFile(), 'line' => $throwable->getLine()]];
        $data = is_product() ? new \stdClass() : $data;
        $data = json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ], JSON_FORCE_OBJECT);
        Log::system('error', '服务内部异常', ['content' => sprintf('%s[%s] in %s', $throwable->getMessage(), $throwable->getLine(), $throwable->getFile())]);
        Log::system('error', '服务内部异常', ['trace' => $throwable->getTraceAsString()]);
        // 阻止异常冒泡
        $this->stopPropagation();

        $log = [
            'method' => $this->request->getMethod(),
            'url' => $this->request->fullUrl(),
            'encrypt' => $this->request->getBody()->getContents(),
            'param' => $this->request->all(),
            'response' => json_decode($data, true),
            'req_header' => $this->request->getHeaders(),
            'resp_header' => $response->getHeaders(),
        ];
        Log::access('info', 'access', $log);
        return $response->withAddedHeader('Content-Type', 'application/json')->withHeader('SERVER-TIMESTAMP', time())->withStatus(200)->withBody(new SwooleStream($data));
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
