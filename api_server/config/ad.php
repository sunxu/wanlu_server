<?php
/**
 * 广告投放
 */
return [
    'test_app' => ['zdjz', 'yfmgj'],// 临时验证新统计回收的应用
    'source' => [
        // 腾讯在点击监测工具中配置 Feedback URL 后自动附加参数，无需伪造宏。
        'tencent' => [
            'name' => '腾讯营销',
            'new_url' => 'https://api.lshx.cc/v1/ad/tx/%s/',
            'click_param' => [],
        ],
        // 巨量
        'juliang' => [
            'name' => '巨量',
            'click_url' => 'https://api.lshx.cc/v1/ad/click/%s/?%s',
            'new_url' => 'https://api.lshx.cc/v1/ad/jl/%s/?%s',
            'click_param' => [
                'ad_id' => '__PROMOTION_ID__',
                'ad_name' => '__PROMOTION_NAME__',
                'project_id' => '__PROJECT_ID__',
                'project_name' => '__PROJECT_NAME__',
                'account_id' => '__ADVERTISER_ID__',
                'imei' => '__IMEI__',
                'oaid' => '__OAID__',
                'android_id' => '__ANDROIDID__',
                'ip' => '__IP__',
                'ua' => '__UA__',
                'model' => '__MODEL__',
                'mac' => '__MAC__',
                'ts' => '__TS__',
                'os' => '__OS__',
                'campaign_id' => '__CAMPAIGN_ID__',
                'campaign_name' => '__CAMPAIGN_NAME__',
                'aid' => '__AID__',
                'aid_name' => '__AID_NAME__',
                'cid' => '__CID__',
                'cid_name' => '__CID_NAME__',
                'csite' => '__CSITE__',
                'convert_id' => '__CONVERT_ID__',
                'request_id' => '__REQUEST_ID__',
                'union_site' => '__UNION_SITE__',
                'callback_url' => '__CALLBACK_URL__',
            ],
        ],
        'kuaishou' => [
            'name' => '快手',
            'click_url' => 'https://api.lshx.cc/v1/ad/kuaishou/%s/?%s',
            'new_url' => 'https://api.lshx.cc/v1/ad/ks/%s/?%s',
            'click_param' => [
                'account_id' => '__ACCOUNTID__',
                'imei' => '__IMEI2__',
                'oaid' => '__OAID__',
                'oaid2' => '__OAID2__',
                'android_id' => '__ANDROIDID2__',
                'ip' => '__IP__',
                'ua' => '__UA__',
                'model' => '__MODEL__',
                'mac' => '__MAC2__',
                'ts' => '__TS__',
                'os' => '__OS__',
                'aid' => '__AID__',
                'did' => '__DID__',
                'dname' => '__DNAME__',
                'cid' => '__CID__',
                'csite' => '__CSITE__',
                'callback_url' => '__CALLBACK__',
            ]
        ],
        'oppo' => [
            'name' => 'OPPO',
            'new_url' => 'https://api.lshx.cc/v1/ad/op/%s/?%s',
            'click_param' => [
                'imei' => '$im$',
                'imei_md5' => '_IMEI_',
                'oaid' => '__OAID__',
                'ts' => '_TS_',
                'android_id' => '__ANDROIDID__',
                'ua' => '$ua$',
                'm' => '$m$',
                'os' => '$os$',
                'ov' => '$ov$',
                'ad' => '$ad$',
                'an' => '$an$',
                'account_id' => '替换成对应帐户ID',
            ]
        ],
        'huawei' => [
            'name' => '华为',
            'new_url' => 'https://api.lshx.cc/v1/ad/hw/%s/',
            'click_param' => [

            ]
        ],
        'vivo' => [
            'name' => 'vivo',
            'new_url' => 'https://api.lshx.cc/v1/ad/vi/%s/?%s',
            'click_param' => [
                'account_id' => '__ADVERTISERID__',
                'android_id' => '__ANDROIDID__',
                'imei' => '__IMEI__',
                'oaid' => '__OAIDPLAIN__',
                'ip' => '__IP__',
                'ua' => '__UA__',
                'ts' => '__TS__',
                'os' => '__OS__',
                'ov' => '__OSVERSION__',
                'model' => '__MODEL__',
                'cid' => '__CREATIVEID__',
                'request_id' => '__REQUESTID__',
            ]
        ],
        'bd' => [
            'name' => '百度',
            'click_url' => 'https://api.lshx.cc/v1/ad/click/%s/?%s',
            'new_url' => 'https://api.lshx.cc/v1/ad/jl/%s/?%s',
            'click_param' => [
                'account_id' => '__USER_ID__',
                'imei' => '__IMEI__',
                'oaid' => '__OAID__',
                'android_id' => '__ANDROIDID__',
                'ip' => '__IP__',
                'ua' => '__UA__',
                'model' => '__MODEL__',
                'ts' => '__TS__',
                'os' => '__OS__',
                'os_version' => '__OS_VERSION__',
                'campaign_id' => '__UNIT_ID__',
                'aid' => '__PLAN_ID__',
                'cid' => '__IDEA_ID__',
                'click_id' => '__CLICK_ID__',
                'callback_url' => '__CALLBACK_URL__',
            ],
        ],
    ],
];
