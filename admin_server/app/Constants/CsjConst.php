<?php

declare(strict_types=1);

namespace App\Constants;

class CsjConst
{
    public const BIDDING_TYPE = [
        '标准' => self::BIDDING_TYPE_COMMON,
        '服务端竞价' => self::BIDDING_TYPE_SERVER,
        '客户端竞价' => self::BIDDING_TYPE_CLIENT,
    ];
    public const BIDDING_TYPE_COMMON = 0;
    public const BIDDING_TYPE_SERVER = 1;
    public const BIDDING_TYPE_CLIENT = 2;

    public const NETWORK_MAP = [
        'csj' => self::NETWORK_CSJ,
        'ylh' => self::NETWORK_YLH,
        'bd' => self::NETWORK_BAIDU,
        'ks' => self::NETWORK_KS,
    ];

    public const YLH_NETWORK_DEFINE = 'ylh_bidding';

    public const NETWORK = [
        '穿山甲' => self::NETWORK_CSJ,
        'admob' => self::NETWORK_ADMOB,
        '优量汇' => self::NETWORK_YLH,
        'mintegral' => self::NETWORK_MINTEGRAL,
        '百度' => self::NETWORK_BAIDU,
        'unity' => self::NETWORK_UNITY,
        '快手' => self::NETWORK_KS,
        'sigmob' => self::NETWORK_SIGMOB,
        '游可盈' => self::NETWORK_YKY,
    ];
    public const NETWORK_CSJ = 1;
    public const NETWORK_ADMOB = 2;
    public const NETWORK_YLH = 3;
    public const NETWORK_MINTEGRAL = 4;
    public const NETWORK_BAIDU = 5;
    public const NETWORK_UNITY = 6;
    public const NETWORK_KS = 7;
    public const NETWORK_SIGMOB = 8;
    public const NETWORK_YKY = 9;

    public const SORT_TYPE = [
        '价格' => self::SORT_TYPE_PRICE,
        '置顶' => self::SORT_TYPE_PRICE,
        '兜底' => self::SORT_TYPE_PRICE,
    ];
    public const SORT_TYPE_PRICE = 1;
    public const SORT_TYPE_TOP = 2;
    public const SORT_TYPE_FLOOR = 3;

    public const STATUS = [
        '启用' => self::STATUS_OPEN,
        '关闭' => self::STATUS_CLOSE,
    ];
    public const STATUS_OPEN = 1;
    public const STATUS_CLOSE = 4;

    public const ORIGIN_TYPE = [
        '不区分' => self::ORIGIN_TYPE_NONE,
        '优量汇模板渲染1.0' => self::ORIGIN_TYPE_TEMPLATE_1,
        '模板渲染' => self::ORIGIN_TYPE_TEMPLATE_1,
        '自渲染' => self::ORIGIN_TYPE_SELF,
        '优量汇模板渲染2.0' => self::ORIGIN_TYPE_TEMPLATE_2,
    ];
    public const ORIGIN_TYPE_NONE = 0;
    public const ORIGIN_TYPE_TEMPLATE_1 = 1;
    public const ORIGIN_TYPE_SELF = 2;
    public const ORIGIN_TYPE_TEMPLATE_2 = 3;

    public const SUB_AD_TYPE = [
        '插屏或半屏' => self::SUB_AD_TYPE_INTER,
        '全屏' => self::SUB_AD_TYPE_FULL,
    ];
    public const SUB_AD_TYPE_INTER = 1;
    public const SUB_AD_TYPE_FULL = 2;

    public const SMART_SORT_SWITCH = [
        '不适用' => self::SMART_SORT_SWITCH_NONE,
        '开启' => self::SMART_SORT_SWITCH_OPEN,
        '关闭' => self::SMART_SORT_SWITCH_CLOSE,
    ];
    public const SMART_SORT_SWITCH_NONE = 0;
    public const SMART_SORT_SWITCH_OPEN = 1;
    public const SMART_SORT_SWITCH_CLOSE = 2;
}