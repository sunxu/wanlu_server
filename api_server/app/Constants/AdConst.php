<?php

namespace App\Constants;

class AdConst
{
    /**
     * 投放渠道
     * @Message("快手")
     */
    public const PUT_SOURCE_KS = 'ks';

    /**
     * 投放渠道
     * @Message("巨量")
     */
    public const PUT_SOURCE_JL = 'jl';

    /**
     * 投放渠道
     * @Message("OPPO")
     */
    public const PUT_SOURCE_OP = 'op';

    /**
     * 投放渠道
     * @Message("华为")
     */
    public const PUT_SOURCE_HW = 'hw';

    /**
     * 投放渠道
     * @Message("荣耀")
     */
    public const PUT_SOURCE_HONOR = 'honor';

    /**
     * 投放渠道
     * @Message("vivo")
     */
    public const PUT_SOURCE_VI = 'vi';

    /**
     * 投放渠道
     * @Message("百度")
     */
    public const PUT_SOURCE_BD = 'bd';

    /**
     * 投放渠道
     * @Message("联运")
     */
    public const PUT_SOURCE_LY = 'ly';

    public const PUT_SOURCE_TX = 'tx';// 腾讯营销

    // 渠道对应标识
    public const PUT_SOURCE_MAP = [
        self::PUT_SOURCE_JL => 1,
        self::PUT_SOURCE_KS => 2,
        self::PUT_SOURCE_OP => 3,
        self::PUT_SOURCE_HW => 4,
        self::PUT_SOURCE_VI => 5,
        self::PUT_SOURCE_BD => 6,
        self::PUT_SOURCE_HONOR => 7,
        self::PUT_SOURCE_TX => 8,
    ];

    // 投放检测链接项目、广告连接符号
    public const PUT_PROJECT_AD_DELIMITER = ':';

    // 投放回调事件类型
    public const PUT_CALLBACK_EVENT = [
        // 激活
        'activate' => [
            self::PUT_SOURCE_JL => 0,
            self::PUT_SOURCE_KS => 1,
            self::PUT_SOURCE_OP => 1,
            self::PUT_SOURCE_HW => 'activate',
            self::PUT_SOURCE_VI => 'ACTIVATION',
            self::PUT_SOURCE_BD => 'activate',
            self::PUT_SOURCE_HONOR => 10001,
        ],
        // 注册
        'register' => [
            self::PUT_SOURCE_JL => 1,
            self::PUT_SOURCE_KS => 2,
            self::PUT_SOURCE_OP => 2,
            self::PUT_SOURCE_HW => 'register',
            self::PUT_SOURCE_VI => 'REGISTER',
            self::PUT_SOURCE_BD => 'register',
            self::PUT_SOURCE_HONOR => 10002,
        ],
        // 支付
        'pay' => [
            self::PUT_SOURCE_JL => 2,
            self::PUT_SOURCE_KS => 3,
            self::PUT_SOURCE_OP => 7,
            self::PUT_SOURCE_HW => 'paid',
            self::PUT_SOURCE_VI => 'PAY',
            self::PUT_SOURCE_BD => 'orders',
            self::PUT_SOURCE_HONOR => 10004,
        ],
        // 次留
        'keep1' => [
            self::PUT_SOURCE_JL => 6,
            self::PUT_SOURCE_KS => 7,
            self::PUT_SOURCE_OP => 4,
            self::PUT_SOURCE_HW => 'retain',
            self::PUT_SOURCE_VI => 'RETENTION_1',
            self::PUT_SOURCE_BD => 'retain_1day',
            self::PUT_SOURCE_HONOR => 10003,
        ],
        // 关键行为
        'key_action' => [
            self::PUT_SOURCE_JL => 25,
            self::PUT_SOURCE_KS => 143,
            self::PUT_SOURCE_OP => 20,// 19-快应用关键行为 20-游戏/应用关键行为
            self::PUT_SOURCE_HW => 'custom',
            self::PUT_SOURCE_VI => 'OTHER',
            self::PUT_SOURCE_BD => 'key_action',
            self::PUT_SOURCE_HONOR => 50012,
        ],
        // 加桌
        'add_desktop' => [
            self::PUT_SOURCE_JL => 25,
            self::PUT_SOURCE_KS => 143,
            self::PUT_SOURCE_OP => 19,// 19-快应用关键行为 20-游戏/应用关键行为
            self::PUT_SOURCE_HW => 'addQuickApp',
            self::PUT_SOURCE_VI => 'ADD_SCREEN',
            self::PUT_SOURCE_BD => 'add_to_desktop',
        ],
        // 快应用-关键行为
        'quick_key_action' => [
            self::PUT_SOURCE_JL => 25,
            self::PUT_SOURCE_KS => 143,
            self::PUT_SOURCE_OP => 19,
            self::PUT_SOURCE_HW => 'custom',
            self::PUT_SOURCE_VI => 'OTHER',
            self::PUT_SOURCE_BD => 'key_action',
        ],
    ];
}