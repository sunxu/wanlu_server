<?php

namespace App\Library\AppStore;

class Xiaomi extends StoreAbstract
{
    protected $domain = "http://api.developer.xiaomi.com";

    protected $company_conf = 'store_xiaomi';

    protected $store_conf = 'store_config_xiaomi';

    /**
     * @inheritDoc
     */
    public function detectChange(): bool
    {
        $storeAppInfo = $this->getStoreAppInfo();
        $appStorage = $this->getAppStorage();
    }

    /**
     * @inheritDoc
     */
    protected function buildMessage(): string
    {
        // TODO: Implement buildMessage() method.
    }

    /**
     * @inheritDoc
     */
    public function getStoreAppInfo(): array
    {
        $appInfo = $this->request('POST', '/devupload/dev/query', ['packageName' => 'com.jyk.android.pss', 'userName' => 'gxbhxx@aliyun.com']);
        var_dump($appInfo);
    }

    protected function request(string $method, string $uri, array $params = []): array
    {
        $params = [
            'RequestData' => json_encode($params),
            'SIG' => $this->getSign($params)
        ];
        var_dump($params);
        return parent::request($method, $uri, ['json' => $params]);
    }

    protected function getSign(array $params): string
    {
        $signs = [
            ['hash' => md5(json_encode($params)), 'name' => 'RequestData']
        ];
        $sig = [
            'password' => 'bhxx1234!',
            'sig' => $signs
        ];
        $sig = json_encode($sig);
        var_dump($sig);
        //公钥加密
        $mch_private_key = "MIICQTCCAaoCCQDab4c81p7I/jANBgkqhkiG9w0BAQQFADBqMQswCQYDVQQGEwJDTjEQMA4GA1UECBMHQmVpSmluZzEQMA4GA1UEBxMHQmVpSmluZzEPMA0GA1UEChMGeGlhb21pMQ0wCwYDVQQLEwRtaXVpMRcwFQYDVQQDEw5kZXYueGlhb21pLmNvbTAeFw0xMzA1MTUwMzMyNDJaFw0yMzA1MTMwMzMyNDJaMGAxCzAJBgNVBAYTAkNOMQswCQYDVQQIEwJCSjELMAkGA1UEBxMCQkoxDjAMBgNVBAoTBWNvbGluMQ4wDAYDVQQLEwVjb2xpbjEXMBUGA1UEAxMOZGV2LnhpYW9taS5jb20wgZ8wDQYJKoZIhvcNAQEBBQADgY0AMIGJAoGBAMBf5LzEiMy0i8LeENXU9v0bTF4coM/kLfK6RvjWS69/6tUxNxJvjDFNbLsmU4xpF3qFY9RI0qyRf79pmKfYUeWomQCM/hKo2lKIbWV7/RVheZhEC2yGbUMRygIzJq3AChBT2MO1a7bA9LINcv+xLmoy5+l3MnVwbVUpWsC/GI59AgMBAAEwDQYJKoZIhvcNAQEEBQADgYEAQfYL1/EdtTXJthFzQxfdKt6y3Ts3b3waTn6od9b+LCcU8EzKHmFOAIpkqIOTvrhB3o/KXEMeMI0PiNHuFnHv9+VGQKiaPFQtb9DsT8iowNDb4G8rdUcoVaczUDbBMG9r5J45UCDxaEzcjp6J0xIS3v11JBK1PtAKHY6RnEJIZuc=";
        $rsa_public = "-----BEGIN CERTIFICATE-----\n".wordwrap($mch_private_key, 64, "\n", true)."\n-----END CERTIFICATE-----";

        $key = openssl_pkey_get_public($rsa_public);
        if (!$key) {
            return('公钥不可用');
        }
        //openssl_public_encrypt 第一个参数只能是string
        //openssl_public_encrypt 第二个参数是处理后的数据
        //openssl_public_encrypt 第三个参数是openssl_pkey_get_public返回的资源类型
        $return_en = openssl_public_encrypt($sig, $crypted, $key);
        if (!$return_en) {
            return('加密失败,请检查RSA秘钥');
        }

//        $decryptData ='';
//
//        $crypto = '';
//
//        $privateKey = "-----BEGIN CERTIFICATE-----\nezorwmrxvenxqbryut0wtec6bho3553rvzv28ryvwq0uwckh03\n-----END CERTIFICATE-----";
//        foreach (str_split($crypted, 128) as $chunk) {
//            openssl_private_decrypt($chunk,$decryptData, $privateKey);
//            $crypto .= $decryptData;
//        }
//        var_dump($crypto);
        return base64_encode($crypted);
    }
}