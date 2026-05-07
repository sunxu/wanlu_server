<?php

declare(strict_types=1);

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

/**
 * @Constants
 */
class StoreHuaweiConst extends AbstractConstants
{
    /**
     * @Message("已上架")
     */
    public const HUAWEI_RELEASE_STATUS_PUTAWAYED = 0;

    /**
     * @Message("上架审核不通过")
     */
    public const HUAWEI_RELEASE_STATUS_PUTAWAY_FAIL = 1;

    /**
     * @Message("已下架（含强制下架）")
     */
    public const HUAWEI_RELEASE_STATUS_DOWAWAYED = 2;

    /**
     * @Message("待上架，预约上架")
     */
    public const HUAWEI_RELEASE_STATUS_PUTAWAYING = 3;

    /**
     * @Message("审核中")
     */
    public const HUAWEI_RELEASE_STATUS_AUDITING = 4;

    /**
     * @Message("升级中")
     */
    public const HUAWEI_RELEASE_STATUS_GRADEING = 5;

    /**
     * @Message("申请下架")
     */
    public const HUAWEI_RELEASE_STATUS_DOWAWAYING = 6;

    /**
     * @Message("草稿")
     */
    public const HUAWEI_RELEASE_STATUS_DRAFT = 7;

    /**
     * @Message("升级审核不通过")
     */
    public const HUAWEI_RELEASE_STATUS_GRADE_FAIL = 8;

    /**
     * @Message("下架审核不通过")
     */
    public const HUAWEI_RELEASE_STATUS_DOWAWAY_FAIL = 9;

    /**
     * @Message("应用被开发者下架")
     */
    public const HUAWEI_RELEASE_STATUS_DOWAWAYED_DEVELOP = 10;

    /**
     * @Message("撤销上架")
     */
    public const HUAWEI_RELEASE_STATUS_PUTAWAY_BACK = 11;

    public const STATUS = [
        self::HUAWEI_RELEASE_STATUS_PUTAWAYED => '已上架',
        self::HUAWEI_RELEASE_STATUS_PUTAWAY_FAIL => '上架审核不通过',
        self::HUAWEI_RELEASE_STATUS_DOWAWAYED => '已下架（含强制下架）',
        self::HUAWEI_RELEASE_STATUS_PUTAWAYING => '待上架，预约上架',
        self::HUAWEI_RELEASE_STATUS_AUDITING => '审核中',
        self::HUAWEI_RELEASE_STATUS_GRADEING => '升级中',
        self::HUAWEI_RELEASE_STATUS_DOWAWAYING => '申请下架',
        self::HUAWEI_RELEASE_STATUS_DRAFT => '草稿',
        self::HUAWEI_RELEASE_STATUS_GRADE_FAIL => '升级审核不通过',
        self::HUAWEI_RELEASE_STATUS_DOWAWAY_FAIL => '下架审核不通过',
        self::HUAWEI_RELEASE_STATUS_DOWAWAYED_DEVELOP => '应用被开发者下架',
        self::HUAWEI_RELEASE_STATUS_PUTAWAY_BACK => '撤销上架',
    ];
}
