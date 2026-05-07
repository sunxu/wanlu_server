<?php

declare(strict_types=1);

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

/**
 * @Constants
 */
class UserConst extends AbstractConstants
{
    /**
     * @Message("默认密码")
     */
    public const DEFAULT_PASSWORD = '123456';

    /**
     * @Message("用户类型")
     */
    public const USER_TYPE = [
        'super' => 1,
        'normal' => 0,
    ];

    /**
     * @Message("账号状态")
     */
    public const ACCOUNT_STATUS = [
        'close' => 0,
        'normal' => 1,
    ];

    /**
     * @Message("设备异常状态")
     */
    public const IS_EXCEPT = [
        'except' => 0, // 异常
        'normal' => 1, // 正常
    ];
}