<?php

declare(strict_types=1);

namespace App\Utils;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Utils\Log;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Ding
{
    private $url = 'https://oapi.dingtalk.com/robot/send?access_token=';

    private $dingToken = null;
    private $dingSecret = null;

    protected $robotCode;

    public function __construct(?string $robotCode = 'warning')
    {
        $this->setRobotCode($robotCode);
    }

    /**
     * 设置机器人
     * @param string $code
     * @return Ding
     */
    public function setRobotCode(string $code): Ding
    {
        if (!is_product()) {
            $code = 'test';
        }

        $this->robotCode = $code;

        return $this;
    }

    /**
     * 设置钉钉配置（用于动态传入token/secret）
     * @param string $token
     * @param string $secret
     * @return Ding
     */
    public function setDingConfig(string $token, string $secret): Ding
    {
        if (empty($token) || empty($secret)) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, "钉钉token和secret不能为空");
        }
        $this->dingToken = $token;
        $this->dingSecret = $secret;
        return $this;
    }

    /**
     * 获取机器人配置
     * @return array
     */
    public function getRobotConf(): array
    {
        $conf = config("ding.{$this->robotCode}");

        if (!$conf) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, "钉钉机器人【{$this->robotCode}】配置不存在");
        }

        return $conf;
    }

    /**
     * 文本消息推送
     * @param string $message
     * @param bool $isAtAll
     * @return bool
     * @throws Exception
     */
    public function pushText(string $message, bool $isAtAll = false): bool
    {
        return $this->request([
            'msgtype' => 'text',
            'text' => [
                'content' => substr($message, 0, 5000)
            ],
            'at' => [
                'isAtAll' => $isAtAll
            ]
        ]);
    }

    /**
     * markdown消息推送
     * @param string $title
     * @param string $message
     * @param bool $isAtAll
     * @return bool
     * @throws Exception
     */
    public function pushMarkdown(string $title, string $message, bool $isAtAll = false): bool
    {
        return $this->request([
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $title,
                'text' => $message
            ],
            'at' => [
                'isAtAll' => $isAtAll
            ]
        ]);
    }

    /**
     * 获取 http 客户端
     * @return Client
     */
    protected function getClient(): Client
    {
        return make(Http::class)->get();
    }

    protected function queryAuth(): array
    {
        $robotConf = $this->getRobotConf();
        // 优先使用动态设置的配置
        $secret = $this->dingSecret ?? data_get($robotConf, 'secret');
        $token = $this->dingToken ?? data_get($robotConf, 'token');

        // 毫秒
        $microTime = round(microtime(true) * 1000);
        // 待签名字符串
        $str = $microTime . PHP_EOL . $secret;
        // 生成签名
        $sign = hash_hmac('sha256', $str, $secret, true);
        // 转换格式
        $sign = base64_encode($sign);

        return [
            'access_token' => $token,
            'timestamp' => $microTime,
            'sign' => $sign
        ];
    }

    /**
     * 请求
     * @param array $data
     * @return bool
     * @throws Exception
     */
    protected function request(array $data): bool
    {
        // 请求地址 - 修改为使用正确的URL格式
        $auth = $this->queryAuth();
        $url = $this->url . $auth['access_token'] . '&timestamp=' . $auth['timestamp'] . '&sign=' . urlencode($auth['sign']);

        $client = $this->getClient();

        // 发送请求
        try {
            $response = $client->request('POST', $url, [
                'json' => $data,
                'verify' => false
            ]);
        } catch (GuzzleException $e) {
            Log::http('error', "请求钉钉失败", ['url' => $url, 'requestBody' => $data, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        return ($content['errcode'] ?? -1) === 0 ? true : false;
    }
}
