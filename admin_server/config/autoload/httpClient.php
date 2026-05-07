<?php

declare(strict_types=1);
return [
    'clients' => [
        'mis' => [
            'base_uri' => env('MIS_HOST'),
        ],
        'umeng' => [
            'base_uri' => 'https://gateway.open.umeng.com',
        ],
        'csj' => [
            'base_uri' => 'https://www.csjplatform.com',
        ],
        'ylh' => [
            'base_uri' => 'https://api.adnet.qq.com',
        ],
        'ks' => [
            'base_uri' => 'https://u.kuaishou.com',
        ],
        'csjmedia' => [
            'base_uri' => 'https://open-api.csjplatform.com',
        ],
        'ubapi' => [
            'base_uri' => 'https://ubapi.baidu.com',
        ]
    ],
];