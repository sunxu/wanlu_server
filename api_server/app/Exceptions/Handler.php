<?php

namespace App\Exceptions;

use App\Services\Helper\Helper;
use App\Services\Helper\Tools\Dding;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{

    /**
     * A list of the exception types that should not be reported.
     * @var array
     */
    protected $dontReport = [
        HttpException::class
    ];

    /**
     * Report or log an exception.
     * @param Exception $exception
     * @return void
     * @throws Exception
     */
    public function report(Exception $exception)
    {
        parent::report($exception);

        $this->pushMessage($exception);
    }

    /**
     * Render an exception into an HTTP response.
     * @param Request $request
     * @param Exception $exception
     * @return Response|JsonResponse
     */
    public function render($request, Exception $exception)
    {
//        if (env('APP_ENV') !== 'production') {
//            $data = [
//                'message' => $exception->getMessage(),
//                'file' => $exception->getFile(),
//                'line' => $exception->getLine()
//            ];
//        }
        return Helper::response(500, $data ?? [], 'Internal Server Error');
    }

    /**
     * Push an exception message.
     * @param Exception $exception
     */
    public function pushMessage(Exception $exception)
    {
        if (env('APP_ENV') === 'production' && $this->shouldReport($exception)) {
            Dding::push((string)$exception);
        }
    }
}
