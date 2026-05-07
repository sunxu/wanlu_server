<?php

declare(strict_types=1);

namespace App\Constants;

class MediaConst
{
    // 媒体类型
    public const MEDIA_TYPE = [
        'csj' => '穿山甲',
        'ylh' => '优量汇',
        'ks' => '快手',
        'hw' => '华为',
        'op' => 'OPPO',
        'vi' => 'vivo',
        'bd' => '百度',
    ];

    // 代码位类型
    public const PLACEMENT_TYPE = [
        'native' => '信息流',
        'banner' => 'banner',
        'splash' => '开屏',
        'inter_full' => '全屏',
        'inter' => '插屏',
        'reword_video' => '激励视频',
    ];
    // 代码位名对应类型
    public const PLACEMENT_NAME_TYPE = [
        '信息流' => 'native',
        '开屏' => 'splash',
        '全' => 'inter_full',
        '半' => 'inter',
        '插屏' => 'inter',
        '激励' => 'reword_video',
    ];

    // 代码位类型-穿山甲
    /*
        1 => '信息流',
        2 => 'Banner',
        3 => '开屏',
        4 => '插屏',
        5 => '激励视频',
        6 => '全屏视频',
        7 => 'Draw信息流',
        8 => '贴片',
        9 => '全插屏',
        10 => '半插屏',
    */
    public const PLACEMENT_TYPE_CSJ = [
        1 => 'native',
        2 => 'banner',
        3 => 'splash',
        4 => 'inter',
        5 => 'reword_video',
        6 => 'inter_full',
        7 => 'native',
        8 => 'inter_full',
        9 => 'inter_full',
        10 => 'inter'
    ];
    // 代码位类型-优量汇
    /*
        FLASH	开屏广告
        FOCUS_POINT	横幅广告
        FLOW_NORMAL	信息流广告
        FLOW_IMMERSION	沉浸式视频流广告
        INSERTION_DETAIL	详情页插入广告
        INSERTION_PASTE	视频贴片广告
        INSERTION_INSERTION	插屏广告
        REWARDED_APP	激励广告
    */
    public const PLACEMENT_TYPE_YLH = [
        'FLOW_NORMAL' => 'native',
        'FOCUS_POINT' => 'banner',
        'FLASH' => 'splash',
        'INSERTION_INSERTION' => 'inter',
        'REWARDED_APP' => 'reword_video',
        'INSERTION_DETAIL' => 'inter_full',
        'INSERTION_PASTE' => 'inter_full',
    ];
    // 代码位类型-快手
    /*
        1: 信息流
        2: 激励视频
        3：全屏
        4：开屏
        5：banner
        6:draw信息流
        13:插屏
        7:feed信息流
    */
    public const PLACEMENT_TYPE_KS = [
        1 => 'native',
        5 => 'banner',
        4 => 'splash',
        13 => 'inter',
        2 => 'reword_video',
        3 => 'inter_full',
        6 => 'native',
        7 => 'native'
    ];

    public const PLACEMENT_TYPE_HW = [
        'native' => 'native',
        'splash' => 'splash',
        'interstitial' => 'inter',
    ];

    public const PLACEMENT_TYPE_OP = [
        8 => 'native',
        4 => 'splash',
        2 => 'inter',
        64 => 'reword_video',
    ];

    public const PLACEMENT_TYPE_BD = [
        '信息流' => 'native',
        '开屏' => 'splash',
        '插屏' => 'inter',
        '全屏视频' => 'inter_full',
    ];
}