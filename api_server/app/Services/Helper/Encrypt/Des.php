<?php
/**
 * DES|加密|解密
 */
declare(strict_types=1);

namespace App\Services\Helper\Encrypt;

class Des
{
    /**
     * 加密
     * @param string $data
     * @param string $key
     * @return string|bool
     */
    public static function encrypt(string $data, string $key)
    {
        // 秘钥
        $key = substr($key, 0, 8);
        // 偏移量
        $iv = $key;
        // 加密
        $result = openssl_encrypt($data, 'DES-CBC', $key, OPENSSL_RAW_DATA, $iv);
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
        // 秘钥
        $key = substr($key, 0, 8);
        // 偏移量
        $iv = $key;
        // 解密
        $result = openssl_decrypt(base64_decode($data), 'DES-CBC', $key, OPENSSL_RAW_DATA, $iv);
        // 返回
        return $result === false ? false : $result;
    }
}
