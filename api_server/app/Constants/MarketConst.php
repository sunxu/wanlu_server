<?php

namespace App\Constants;

class MarketConst
{
    /**
     * 转化事件类型
     */
    const KS_EVENT_TYPE = [
        'hour_keep' => 181,//24小时留存
    ];

    /**
     * 24小时留存状态
     */
    const HOUR_KEEP = [
        'none' => 0,//未留存
        'keep' => 1//已留存
    ];

    /**
     * 投放平台
     */
    const PLATFORM = [
        'jl' => 1,//巨量
        'ks' => 2//快手
    ];
}