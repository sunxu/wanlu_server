<?php

declare(strict_types=1);

return [
    'base_url' => 'https://{domain}',
    'source' => [
        // 腾讯在点击监测工具中配置 Feedback URL 后自动附加参数，无需伪造宏。
        [
            'name' => '腾讯营销',
            'path' => '/v1/ad/tx/%s/',
            'params' => [],
        ],
        [
            'name' => '巨量',
            'path' => '/v1/ad/jl/%s/?%s',
            'params' => [
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
        [
            'name' => '快手',
            'path' => '/v1/ad/ks/%s/?%s',
            'params' => [
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
        [
            'name' => 'OPPO',
            'path' => '/v1/ad/op/%s/?%s',
            'params' => [
                'imei' => '$im$',
                'imei_md5' => '_IMEI_',
                'oaid' => '__OAID__',
                'ts' => '_TS_',
                'android_id' => '__ANDROIDID__',
                'ip' => '__IP__',
                'ua' => '$ua$',
                'm' => '$m$',
                'os' => '$os$',
                'ov' => '$ov$',
                'adid' => '__ADID__',
                'ad' => '$ad$',
                'an' => '$an$',
                'account_id' => '替换成对应帐户ID',
            ]
        ],
        [
            'name' => '华为',
            'path' => '/v1/ad/hw/%s/',
            'params' => [

            ]
        ],
        [
            'name' => 'vivo',
            'path' => '/v1/ad/vi/%s/?%s',
            'params' => [
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
        [
            'name' => '百度',
            'path' => '/v1/ad/bd/%s/?%s',
            'params' => [
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
            ]
        ],
        [
            'name' => '荣耀',
            'path' => '/v1/ad/honor/%s/?%s',
            'params' => [
                'account_id' => '__ADVERTISER_ID__',
                'oaid' => '__OAID__',
                'ip' => '__IP__',
                'os' => '__OS__',
                'request_id' => '__REQUESTID__',
                'track_id' => '__TRACK_ID__',
            ]
        ],
    ],
];
