<?php
/**
 * RSA|加密|解密|加签|验签
 * 秘钥长度为 2048位(bit) 秘钥格式为 PKCS1
 * PKCS8(JAVA适用) 请转换为 PKCS1(非JAVA适用)
 * 工具下载 https://docs.open.alipay.com/291/105971
 */
declare(strict_types=1);

namespace App\Services\Helper\Encrypt;

class Rsa
{
    /**
     * 公钥加密
     * @param string $data
     * @param string $key
     * @return string|bool
     */
    public static function encrypt(string $data, string $key)
    {
        // 格式化
        $key = self::format($key, 'public');
        // 解析资源
        $resource = openssl_pkey_get_public($key);
        // 解析失败
        if ($resource === false) {
            return false;
        }
        // 加密
        $result = openssl_public_encrypt($data, $encrypt, $resource);
        // 关闭资源
        openssl_free_key($resource);
        // 返回
        return $result ? base64_encode($encrypt) : false;
    }

    /**
     * 私钥解密
     * @param string $data
     * @param string $key
     * @return string|bool
     */
    public static function decrypt(string $data, string $key)
    {
        // 格式化
        $key = self::format($key, 'private');
        // 解析资源
        $resource = openssl_pkey_get_private($key);
        // 解析失败
        if ($resource === false) {
            return false;
        }
        // 解密
        $result = openssl_private_decrypt(base64_decode($data), $decrypt, $resource);
        // 关闭资源
        openssl_free_key($resource);
        // 返回
        return $result ? $decrypt : false;
    }

    /**
     * 私钥加签
     * @param string $data
     * @param string $key
     * @return string|bool
     */
    public static function sign(string $data, string $key)
    {
        // 格式化
        $key = self::format($key, 'private');
        // 解析资源
        $resource = openssl_pkey_get_private($key);
        // 解析失败
        if ($resource === false) {
            return false;
        }
        // 加签
        $result = openssl_sign($data, $sign, $resource, OPENSSL_ALGO_SHA256);
        // 关闭资源
        openssl_free_key($resource);
        // 返回
        return $result ? base64_encode($sign) : false;
    }

    /**
     * 公钥验签
     * @param string $data
     * @param string $key
     * @param string $sign
     * @return bool
     */
    public static function verify(string $data, string $key, string $sign)
    {
        // 格式化
        $key = self::format($key, 'public');
        // 解析资源
        $resource = openssl_pkey_get_public($key);
        // 解析失败
        if ($resource === false) {
            return false;
        }
        // 验签
        $result = openssl_verify($data, base64_decode($sign), $resource, OPENSSL_ALGO_SHA256);
        // 关闭资源
        openssl_free_key($resource);
        // 返回
        return boolval($result);
    }

    /**
     * 格式化秘钥
     * @param string $key
     * @param string $type
     * @return string
     */
    private static function format(string $key, string $type): string
    {
        // 秘钥标识
        $code = $type === 'public' ? 'PUBLIC' : 'RSA PRIVATE';
        // 格式化
        $format = sprintf("-----BEGIN %s KEY-----", $code);
        $format .= "\n" . chunk_split($key, 64, "\n");
        $format .= sprintf("-----END %s KEY-----", $code);
        // 返回
        return $format;
    }
}
