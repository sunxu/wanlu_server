<?php

declare(strict_types=1);

namespace App\Utils;

use App\Model\App;

class Aes
{
    public static function EncryptResponse($params): string
    {
        if(is_local()){
            return json_encode($params);
        }
        $appId = request()->getHeaderLine('x-appid');
        $appInfo = (new App())->getAppInfo(intval($appId));
        $secret = data_get($appInfo, 'params_secret_key');
        $encrypt = openssl_encrypt(json_encode($params), 'AES-128-ECB', $secret, OPENSSL_RAW_DATA);
        if(!$encrypt){
            return '';
        }
        return base64_encode($encrypt);
    }
}