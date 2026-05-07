<?php

declare(strict_types=1);

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

/**
 * @Constants
 */
class StoreVivoConst extends AbstractConstants
{
    /**
     * @Message("草稿")
     */
    public const VIVO_AUDIT_DRAFT = 1;

    /**
     * @Message("待审核")
     */
    public const VIVO_AUDIT_WAIT = 2;

    /**
     * @Message("审核通过")
     */
    public const VIVO_AUDIT_PASS = 3;

    /**
     * @Message("审核不通过")
     */
    public const VIVO_AUDIT_FAIL = 4;

    /**
     * @Message("撤销审核")
     */
    public const VIVO_AUDIT_BACK = 5;

    public const AUDIT = [
        self::VIVO_AUDIT_DRAFT => '草稿',
        self::VIVO_AUDIT_WAIT => '待审核',
        self::VIVO_AUDIT_PASS => '审核通过',
        self::VIVO_AUDIT_FAIL => '审核不通过',
        self::VIVO_AUDIT_BACK => '撤销审核',
    ];

    /**
     * @Message("待上架")
     */
    public const VIVO_PUTAWAY_WAIT = 0;

    /**
     * @Message("已上架")
     */
    public const VIVO_PUTAWAY_ED = 1;

    /**
     * @Message("已下架")
     */
    public const VIVO_PUTAWAY_DOW = 2;

    public const PUT_AWAY = [
        self::VIVO_PUTAWAY_WAIT => '待上架',
        self::VIVO_PUTAWAY_ED => '已上架',
        self::VIVO_PUTAWAY_DOW => '已下架',
    ];
}
