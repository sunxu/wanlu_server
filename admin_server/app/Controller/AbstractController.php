<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\ErrorCode;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Psr7ResponseInterface;

abstract class AbstractController
{
    /**
     * @Inject
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @Inject
     * @var RequestInterface
     */
    protected $request;

    /**
     * @Inject
     * @var ResponseInterface
     */
    protected $response;

    public function responseSuccess($data = null): Psr7ResponseInterface
    {
        return $this->response->json([
            'code' => ErrorCode::OK,
            'msg' => ErrorCode::getMessage(ErrorCode::OK),
            'data' => $data ?: new \stdClass(),
        ]);
    }

    public function responseError(int $code = ErrorCode::SERVER_ERROR, ?string $message = ''): Psr7ResponseInterface
    {
        return $this->response->json([
            'code' => $code,
            'msg' => $message ?: ErrorCode::getMessage($code),
            'data' => new \stdClass(),
        ]);
    }

    public function responseRecord($data = []): Psr7ResponseInterface
    {
        return $this->response->json([
            'code' => ErrorCode::OK,
            'msg' => ErrorCode::getMessage(ErrorCode::OK),
            'data' => [
                'records' => $data,
            ],
        ]);
    }

    public function responsePaginator($data, $paginator): Psr7ResponseInterface
    {
        $data = [
            'records' => $data,
            'current' => $paginator->currentPage(),
            'page_size' => $paginator->count(),
            'total' => $paginator->total(),
        ];
        return $this->response->json([
            'code' => ErrorCode::OK,
            'msg' => ErrorCode::getMessage(ErrorCode::OK),
            'data' => $data,
        ]);
    }
}
