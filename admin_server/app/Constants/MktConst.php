<?php

declare(strict_types=1);

namespace App\Constants;

use App\Library\Mkt\MktBd;
use App\Library\Mkt\MktHw;
use App\Library\Mkt\MktJl;
use App\Library\Mkt\MktKs;
use App\Library\Mkt\MktOp;
use App\Library\Mkt\MktVi;
use App\Library\Mkt\MktTx;

class MktConst
{
    // 队列
    public const QUEUE_KEY = 'mkt';

    // 投放平台
    public const PLATFORM_JL = 'jl';// 巨量
    public const PLATFORM_KS = 'ks';// 快手
    public const PLATFORM_HW = 'hw';// 华为
    public const PLATFORM_OP = 'op';// OPPO
    public const PLATFORM_VI = 'vi';// VIVO
    public const PLATFORM_BD = 'bd';// 百度
    public const PLATFORM_TX = 'tx';// 腾讯营销

    public const PLATFORM = [
        self::PLATFORM_JL => '巨量',
        self::PLATFORM_KS => '快手',
        self::PLATFORM_HW => '华为',
        self::PLATFORM_OP => 'OPPO',
        self::PLATFORM_VI => 'vivo',
        self::PLATFORM_BD => '百度',
        self::PLATFORM_TX => '腾讯营销',
    ];

    public const PLATFORM_SOURCE = [
        self::PLATFORM_JL => 1,
        self::PLATFORM_KS => 2,
        self::PLATFORM_OP => 3,
        self::PLATFORM_HW => 4,
        self::PLATFORM_VI => 5,
        self::PLATFORM_BD => 6,
        self::PLATFORM_TX => 8,
    ];

    // 投放服务
    public const PLATFORM_SERVICE = [
        self::PLATFORM_JL => MktJl::class,
        self::PLATFORM_KS => MktKs::class,
        self::PLATFORM_HW => MktHw::class,
        self::PLATFORM_OP => MktOp::class,
        self::PLATFORM_VI => MktVi::class,
        self::PLATFORM_BD => MktBd::class,
        self::PLATFORM_TX => MktTx::class,
    ];

    // 百度投放拼接字符标识
    public const PLATFORM_BD_DELIMITER = '__##__';

    public const LAUNCH_TYPE = [
        0 => '次留双出',
        1 => '关键行为',
    ];

    /**
     * 报表小时字段信息
     * @var array
     */
    public const HOUR_INFO_KEYS = [
        'cost', 'show', 'avg_show_cost', 'click', 'avg_click_cost',
        'click_rate', 'convert', 'convert_cost', 'convert_rate', 'active',
        'active_cost', 'active_rate', 'next_day_open', 'next_day_open_cost', 'next_day_open_rate',
        'key_action', 'key_action_cost', 'key_action_rate',
    ];

    // 钉钉机器人
    public const ROBOT_CODE = 'mkt_put';

    // 规则运算操作符
    public const RULE_OPERATE = [
        'lt' => '小于',
        'le' => '小于等于',
        'eq' => '等于',
        'gt' => '大于',
        'ge' => '大于等于',
    ];

    // 规则阈值类型
    public const RULE_THRESHOLD_TYPE = [
        'active_cost' => '激活成本',
        'convert_cost' => '转化成本',
        'action_rate' => '关键行为率',
        'cvr' => 'CVR',
        'arpu' => 'ARPU',
        'roi' => 'ROI',
    ];

    // 阈值类型对应报表字短
    public const RULE_THRESHOLD_TYPE_KEY = [
        'active_cost' => 'active_cost',
        'convert_cost' => 'convert_cost',
        'action_rate' => 'key_action_rate',
        'cvr' => 'convert_rate',
        'arpu' => 'arpu',
        'roi' => 'roi',
    ];

    // 关键行为自定义默认值
    public const ACTION_VALUE_DEFAULT = [
        'arpu2.04ipu25' => [
            'op' => 'and',
            'ecpm' => [
                'ad_type' => ['full_video', 'inter_full'],
                'num' => 25,
                'value' => 0
            ],
            'arpu' => [
                'ad_type' => [],
                'value' => 2.04
            ]
        ],
        'arpu2.11ipu23' => [
            'op' => 'and',
            'ecpm' => [
                'ad_type' => ['full_video', 'inter_full'],
                'num' => 23,
                'value' => 0
            ],
            'arpu' => [
                'ad_type' => [],
                'value' => 2.11
            ]
        ],
        'arpu2.18ipu21' => [
            'op' => 'and',
            'ecpm' => [
                'ad_type' => ['full_video', 'inter_full'],
                'num' => 21,
                'value' => 0
            ],
            'arpu' => [
                'ad_type' => [],
                'value' => 2.18
            ]
        ],
        'arpu2.25ipu19' => [
            'op' => 'and',
            'ecpm' => [
                'ad_type' => ['full_video', 'inter_full'],
                'num' => 19,
                'value' => 0
            ],
            'arpu' => [
                'ad_type' => [],
                'value' => 2.25
            ]
        ],
        'arpu2.32ipu17' => [
            'op' => 'and',
            'ecpm' => [
                'ad_type' => ['full_video', 'inter_full'],
                'num' => 17,
                'value' => 0
            ],
            'arpu' => [
                'ad_type' => [],
                'value' => 2.32
            ]
        ],
        'arpu2.40ipu15' => [
            'op' => 'and',
            'ecpm' => [
                'ad_type' => ['full_video', 'inter_full'],
                'num' => 15,
                'value' => 0
            ],
            'arpu' => [
                'ad_type' => [],
                'value' => 2.40
            ]
        ],
    ];

}