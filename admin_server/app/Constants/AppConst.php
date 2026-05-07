<?php

declare(strict_types=1);

namespace App\Constants;

class AppConst
{
    // 应用大类
    public const APP_TYPE_TOOLS = 1000;// 工具类应用
    public const APP_TYPE_MIS = 2000;// 网赚类应用
    public const APP_TYPE_VIDEO = 3000;// 短剧

    // 应用子类
    public const APP_SUB_TYPE_WIFI = 1001; // WiFi
    public const APP_SUB_TYPE_CLEAR = 1002; // 清理
    public const APP_SUB_TYPE_WEATHER = 1003; // 天气
    public const APP_SUB_TYPE_BOX = 1004; // 工具盒
    public const APP_SUB_TYPE_PAY = 1005; // 付费工具
    public const APP_SUB_TYPE_VIDEO = 2001; // 短视频
    public const APP_SUB_TYPE_MUSIC = 2002; // 猜歌
    public const APP_SUB_TYPE_PLAYLET = 3001;// 短剧

    public const APP_STATUS_ENABLE = 1;// 启用
    public const APP_STATUS_DISABLE = 0;// 禁用

    // 应用投放状态
    public const APP_PUT_STATUS_STOP = 0;// 停投
    public const APP_PUT_STATUS_SELF = 1;// 自投
    public const APP_PUT_STATUS_AGENCY = 2;// 联运代投

    public const APP_PUT_STATUS_ARR = [
        self::APP_PUT_STATUS_STOP,
        self::APP_PUT_STATUS_SELF,
        self::APP_PUT_STATUS_AGENCY,
    ];

    public const APP_PUT_STATUS_MAP = [
        self::APP_PUT_STATUS_STOP => '停投',
        self::APP_PUT_STATUS_SELF => '自投',
        self::APP_PUT_STATUS_AGENCY => '联运代投',
    ];

    public const APP_DB_MAP = [//DB类型
        self::APP_TYPE_TOOLS => 'tools',//工具数据库
        self::APP_TYPE_MIS => 'mis',//网赚数据库
    ];

    public const STRATEGY_KEY_COMMON = 'common';//正常
    public const STRATEGY_KEY_PATROL = 'patrol';//审核
    public const STRATEGY_KEY_AUDIT = 'audit';//巡检
    public const STRATEGY_KEY_EXCEPT = 'except';//异常设备
    public const STRATEGY_KEY_NAME_MAP = [
        self::STRATEGY_KEY_COMMON => '正常',
        self::STRATEGY_KEY_PATROL => '审核',
        self::STRATEGY_KEY_AUDIT => '巡检',
        self::STRATEGY_KEY_EXCEPT => '异常设备'
    ];

    const PLATFORM_KS = 'ks';//快手
    const PLATFORM_CSJ = 'csj';//穿山甲
    const PLATFORM_YLH = 'ylh';//优量汇
    const PLATFORM_HW = 'hw';//华为
    const PLATFORM_OP = 'op';
    const PLATFORM_VI = 'vi';
    const PLATFORM_BD = 'bd';

    const PLATFORM_TOPON = 'topon';//Topon
    const PLATFORM_NAME_MAPS = [
        self::PLATFORM_CSJ => '穿山甲',
        self::PLATFORM_YLH => '优量汇',
        self::PLATFORM_KS => '快手',
        self::PLATFORM_HW => '华为',
        self::PLATFORM_OP => 'OPPO',
        self::PLATFORM_VI => 'vivo',
        self::PLATFORM_BD => '百度',
        self::PLATFORM_TOPON => 'Topon',
    ];

    const PLATFORM_OLD_MAPS = [
        self::PLATFORM_CSJ => 1,
        self::PLATFORM_YLH => 2,
        self::PLATFORM_KS => 3,
        self::PLATFORM_HW => 4,
        self::PLATFORM_OP => 5,
        self::PLATFORM_VI => 6,
        self::PLATFORM_BD => 7,
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
    const SOURCE_HW = 'hw';//华为
    const SOURCE_OP = 'op';//OPPO
    const SOURCE_VI = 'vi';//vivo
    const SOURCE_NAME_MAP = [
        self::SOURCE_JL => '巨量',
        self::SOURCE_KS => '快手',
        self::SOURCE_HW => '华为',
        self::SOURCE_OP => 'OPPO',
        self::SOURCE_VI => 'vivo',
        self::SOURCE_LY => '联运',
    ];
    const SOURCE_OLD_MAP = [//老数据兼容
        self::SOURCE_JL => 1,
        self::SOURCE_KS => 2,
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

    const TEST_CLSC_ADVERTISER_ID = 'test-clsc-001';//测试次留双出广告主ID
    const TEST_GJXW_ADVERTISER_ID = 'test-gjxw-001';//测试关键行为广告主ID
    const LAUNCH_TYPE_TEST_ADVERTISER_ID_MAP = [//和投放方式的对应的关系
        self::LAUNCH_TYPE_GJXW => self::TEST_GJXW_ADVERTISER_ID,
        self::LAUNCH_TYPE_CLSC => self::TEST_CLSC_ADVERTISER_ID
    ];

    const DAILY_SECRET = 'Fz0rApjiy8PgxVmJ';//日报查看密钥

    // 广告主列表
    const TOOLS_MKT_ADVERTISER_TABLE = 'com_mkt_advertiser';
}