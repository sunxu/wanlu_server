<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/17
 */

namespace App\Constants;

class PlacementConst
{
    const PLATFORM_KS = 'ks';//快手
    const PLATFORM_CSJ = 'csj';//穿山甲
    const PLATFORM_YLH = 'ylh';//优量汇
    const PLATFORM_TOPON = 'topon';//Topon
    const PLATFORM_NAME_MAPS = [
        self::PLATFORM_KS => '快手',
        self::PLATFORM_CSJ => '穿山甲',
        self::PLATFORM_YLH => '优量汇',
        self::PLATFORM_TOPON => 'Topon',
    ];

    const PLATFORM_OLD_MAPS = [
        self::PLATFORM_KS => 3,
        self::PLATFORM_CSJ => 1,
        self::PLATFORM_YLH => 2,
    ];

    const AD_TYPE_NATIVE = 'native';//代码位类型 - 信息流
    const AD_TYPE_INTER = 'inter';//代码位类型 - 插屏
    const AD_TYPE_INTER_FULL = 'inter_full';//代码位类型 - 全屏插屏
    const AD_TYPE_FULL_VIDEO = 'full_video';//代码位类型 - 全屏视频
    const AD_TYPE_SPLASH = 'splash';//代码位类型 - 开屏
    const AD_TYPE_REWORD_VIDEO = 'reword_video';//代码位类型 - 激励视频
    const AD_TYPE_NAME_MAP = [
        self::AD_TYPE_NATIVE => '信息流',
        self::AD_TYPE_INTER => '插屏',
        self::AD_TYPE_INTER_FULL => '全屏插屏',
        self::AD_TYPE_FULL_VIDEO => '全屏视频',
        self::AD_TYPE_SPLASH => '开屏',
        self::AD_TYPE_REWORD_VIDEO => '激励视频',
    ];

    /*投放渠道*/
    const SOURCE_KS = 'ks';//快手
    const SOURCE_JL = 'jl';//巨量
    const SOURCE_LY = 'ly';//联运
    const SOURCE_NAME_MAP = [
        self::SOURCE_KS => '快手',
        self::SOURCE_JL => '巨量',
        self::SOURCE_LY => '联运',
    ];
    const SOURCE_OLD_MAP = [//老数据兼容
        self::SOURCE_KS => 2,
        self::SOURCE_JL => 1,
    ];

    /*投放方式*/
    const LAUNCH_TYPE_GJXW = 1;//关键行为
    const LAUNCH_TYPE_CLSC = 0;//次留双出
    const LAUNCH_TYPE_NAME_MAP = [
        self::LAUNCH_TYPE_GJXW => '关键行为',
        self::LAUNCH_TYPE_CLSC => '次留双出',
    ];
    const LAUNCH_TYPE_OLD_MAP = [//老数据兼容
        self::LAUNCH_TYPE_GJXW => 1,
        self::LAUNCH_TYPE_CLSC => 2,
    ];
}