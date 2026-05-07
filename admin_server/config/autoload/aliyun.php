<?php

declare(strict_types=1);

return [
    'sls_access_key_id' => env('ALIYUN_SLS_ACCESS_KEY_ID'),//阿里云账号ID
    'sls_access_key_secret' => env('ALIYUN_SLS_ACCESS_KEY_SECRET'),//阿里云账号SECRET
    'sls_point_inner' => env('ALIYUN_SLS_POINT_INNER'),//sls 内网地址
    'sls_point_outer' => env('ALIYUN_SLS_POINT_OUTER'),//sls 外网地址
    'sls_project' => env('ALIYUN_SLS_PROJECT'),//sls project名
    'sls_logstore_ad_event' => env('ALIYUN_SLS_LOGSTORE_AD_EVENT'),//sls 广告打点logstore名
    'sls_logstore_ad_duration' => env('ALIYUN_SLS_LOGSTORE_AD_DURATION'),//sls 时长记录logstore名
];
