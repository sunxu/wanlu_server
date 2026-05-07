<?php

declare(strict_types=1);

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

/**
 * @Constants
 */
class RedisConst extends AbstractConstants
{
    /**
     *
     * @Message ("网赚用户相关")
     */
    public const POOL_NAME_MIS_USER = 'mis_user';

    /**
     * @Message ("网赚广告相关")
     */
    public const POOL_NAME_MIS_AD = 'mis_ad';

    /**
     * @Message ("app缓存")
     */
    public const APP_CACHE = 'app:hash';

    /**
     * 第一个%s tableName
     * 第二个%s 查询条件KEY
     * @Message("Model缓存KEY")
     */
    public const MODEL_CACHE_KEY = 'model_cache:%s:%s';

    /**
     * 第一个%s 用户ID
     * 第二个%s appcode
     * @Message("用户信息")
     */
    public const USER_INFO = 'mis:user:string:%s:%s';

    /**
     * @Message ("华为 token")
     */
    public const HUAWEI_TOKEN = 'huawei:token';

    /**
     * @Message ("华为变现 token")
     */
    public const HUAWEI_MEDIA_TOKEN = 'huawei:media:token';

    /**
     * @Message ("oppo token")
     */
    public const OPPO_TOKEN = 'oppo:token';
}