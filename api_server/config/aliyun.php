<?php

declare(strict_types=1);
/**
 * 阿里云相关配置文件
 * Author: jyk-wsy
 * Date: 2022/3/30
 */

return [
    'access_key_id' => env('ALIYUN_ACCESS_KEY_ID'),//阿里云账号ID
    'access_key_secret' => env('ALIYUN_ACCESS_KEY_SECRET'),//阿里云账号SECRET
    'sls_point_inner' => env('ALIYUN_SLS_POINT_INNER'),//sls 内网地址
    'sls_point_outer' => env('ALIYUN_SLS_POINT_OUTER'),//sls 外网地址
    'sls_project' => env('ALIYUN_SLS_PROJECT'),//sls 外网地址

    'oss_public_endpoint' => env('OSS_PUBLIC_ENDPOINT'),
];