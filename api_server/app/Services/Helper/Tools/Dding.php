<?php
/**
 * 钉钉
 */

declare(strict_types=1);

namespace App\Services\Helper\Tools;

use App\Services\Helper\Helper;

class Dding
{
    /**
     * 消息发送地址
     * @var string
     */
    // private static $requestUrl = 'https://oapi.dingtalk.com/robot/send';

    /**
     * 消息推送
     * @param string $message
     * @return array
     */
    public static function push(string $message): array
    {
        // 配置文件
        // $configArr = config('ding');
        // $arr = array_values($configArr);
        // if (count($arr) < 1) {
        //     return Helper::return(1000);
        // }
        // $config = $arr[mt_rand(0, count($arr) - 1)];
        // // 毫秒
        // $microTime = round(microtime(true) * 1000);
        // // 待签名字符串
        // $str = $microTime . PHP_EOL . $config['sign_key'];
        // // 生成签名
        // $sign = hash_hmac('sha256', $str, $config['sign_key'], true);
        // // 转换格式
        // $sign = base64_encode($sign);
        // // 头信息
        // $header = ['Content-Type:application/json;charset=utf-8'];
        // // 请求地址
        // $url = sprintf('%s?%s', self::$requestUrl, http_build_query([
        //     'access_token' => $config['access_token'],
        //     'timestamp' => $microTime,
        //     'sign' => $sign
        // ]));
        // // 格式化
        // $format = [
        //     'msgtype' => 'text',
        //     'text' => ['content' => substr($message, 0, 5000)],
        //     'at' => ['isAtAll' => true]
        // ];
        // // 发送请求
        // $result = Helper::request($url, 'POST', json_encode($format), $header);
        // // 发送失败
        // if ($result['err_no'] !== 0 || empty($result['result'])) {
        //     return Helper::return(1000);
        // }
        // // 转换格式
        // $result = json_decode($result['result'], true);
        // // 响应失败
        // if (empty($result['errcode']) || intval($result['errcode']) !== 0) {
        //     return Helper::return(1000, $result);
        // }
        // 发送成功
        return Helper::return(0);
    }
}
