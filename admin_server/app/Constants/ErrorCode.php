<?php

declare(strict_types=1);

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

/**
 * @Constants
 */
class ErrorCode extends AbstractConstants
{
    /**
     * @Message("OK")
     */
    public const OK = 0;

    /**
     * @Message("服务器内部错误")
     */
    public const SERVER_ERROR = 101500;

    /**
     * @Message("未登录")
     */
    public const NOT_LOGIN = 410;

    /**
     * @Message("参数错误")
     */
    public const PARAMS_ERROR = 101410;

    /**
     * @Message("签名错误")
     */
    public const SIGN_ERROR = 420;

    /**
     * @Message("签名过期")
     */
    public const SIGN_EXPIRE = 421;

    /**
     * @Message("重复请求")
     */
    public const SIGN_REPETITION = 422;

    /**
     * @Message("无权限")
     */
    public const NONE_AUTH = 411;
}
