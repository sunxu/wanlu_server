<?php

return [
    'callback_domain' => env('CALLBACK_DOMAIN', 'https://test-api.5gwifilink.com'),
    'mchid' => [
        '1623882753' => [
            'mchid' => '1623882753',//商户号
            'serial' => env('WECHAT_PAY_XYRS_SERIAL'),//「商户API证书」的「证书序列号」
            'privateKeyFile' => env('WECHAT_PAY_XYRS_PRIKEY'),//商户API私钥
            'certificateFile' => env('WECHAT_PAY_XYRS_CERT'),//微信支付平台证书
            'apikey' => env('WECHAT_PAY_XYRS_APIKEY'),//APIV3密钥
        ],
        '1626335475' => [
            'mchid' => '1626335475',//商户号
            'serial' => env('WECHAT_PAY_BHXX_SERIAL'),//「商户API证书」的「证书序列号」
            'privateKeyFile' => env('WECHAT_PAY_BHXX_PRIKEY'),//商户API私钥
            'certificateFile' => env('WECHAT_PAY_BHXX_CERT'),//微信支付平台证书
            'apikey' => env('WECHAT_PAY_BHXX_APIKEY'),//APIV3密钥
        ]
    ],
];
