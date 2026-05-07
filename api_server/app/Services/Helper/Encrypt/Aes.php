<?php
/**
 * AES|加密|解密
 */
declare(strict_types=1);

namespace App\Services\Helper\Encrypt;

class Aes
{
    /**
     * 加密
     * @param string $data
     * @param string $key
     * @return string|bool
     */
    public static function encrypt(string $data, string $key)
    {
        // 加密
        $result = openssl_encrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        // 返回
        return $result === false ? false : base64_encode($result);
    }

    /**
     * 解密
     * @param string $data
     * @param string $key
     * @return string|bool
     */
    public static function decrypt(string $data, string $key)
    {
        // 解密
        $result = openssl_decrypt(base64_decode($data), 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        // 返回
        return $result === false ? false : $result;
    }
}