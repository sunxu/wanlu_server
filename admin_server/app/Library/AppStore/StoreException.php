<?php

declare(strict_types=1);

namespace App\Library\AppStore;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use Throwable;

class StoreException extends BusinessException
{
    public function __construct(string $message = null, int $code = ErrorCode::SERVER_ERROR, Throwable $previous = null)
    {
        if (is_null($message)) {
            $message = ErrorCode::getMessage($code) ?? '服务器内部错误';
        }

        parent::__construct($code, $message, $previous);
    }
}