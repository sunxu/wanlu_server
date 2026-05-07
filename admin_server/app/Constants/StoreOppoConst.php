<?php

declare(strict_types=1);

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

/**
 * @Constants
 */
class StoreOppoConst extends AbstractConstants
{
    /**
     * @Message("未冻结")
     */
    public const OPPO_IS_FREEZE = 0;

    /**
     * @Message("已冻结")
     */
    public const OPPO_NOT_FREEZE = 1;

    public const FREEZE = [
        self::OPPO_IS_FREEZE => '未冻结',
        self::OPPO_NOT_FREEZE => '已冻结',
    ];

    /**
     * @Message("上架")
     */
    public const OPPO_ON_LINE = 1;

    /**
     * @Message("下架")
     */
    public const OPPO_OUT_LINE = 2;

    public const ON_LINE = [
        self::OPPO_ON_LINE => '上架',
        self::OPPO_OUT_LINE => '下架',
    ];
}
