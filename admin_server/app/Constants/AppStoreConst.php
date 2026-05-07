<?php

declare(strict_types=1);

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

/**
 * @Constants
 */
class AppStoreConst extends AbstractConstants
{
    /**
     * @Message("是否开启通知")
     */
    public const IS_ENABLE_NOTIFY = [
        'enable' => 1,
        'disable' => 0
    ];
}
