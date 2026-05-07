<?php

declare(strict_types=1);

namespace App\Utils;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Cache\Cache;

class Lark
{
    private $url = 'https://open.larksuite.com/open-apis/bot/v2/hook/';

    protected $robotCode;

    public function __construct(?string $robotCode = 'warning')
    {
        $this->setRobotCode($robotCode);
    }

    /**
     * 设置机器人
     * @param string $code
     * @return Lark
     */
    public function setRobotCode(string $code): Lark
    {
        if (!is_product()) {
            $code = 'test';
        }

        $this->robotCode = $code;
        return $this;
    }

    /**
     * 获取机器人配置
     * @return array
     */
    public function getRobotConf(): array
    {
        $conf = config("lark.{$this->robotCode}");

        if (!$conf) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, "Lark机器人【{$this->robotCode}】配置不存在");
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
            'msg_type' => 'text',
            'content' => [
                'text' => ($isAtAll ? '<at user_id="all">所有人</at>' : '') . substr($message, 0, 5000)
            ],
        ]);
    }

    /**
     * markdown消息推送
     * @param string $title
     * @param string $content
     * @param bool $isAtAll
     * @return bool
     * @throws Exception
     */
    public function pushRichText(string $title, string $content, bool $isAtAll = false): bool
    {
        $contentExample = [
            [
                [
                    'tag' => 'text1',
                    'text' => 'this is text content'
                ],
                [
                    'tag' => 'a',
                    'text' => 'this is text href',
                    'href' => 'http://www.example.com/'
                ],
            ],
            [
                [
                    'tag' => 'text2',
                    'text' => 'this is text content'
                ],
                [
                    'tag' => 'a',
                    'text' => 'this is text href',
                    'href' => 'http://www.example.com/'
                ],
                [
                    'tag' => 'image',
                    'image_key' => 'd640eeea-4d2f-4cb3-88d8-c964fab53987',
                    'width' => 300,
                    'height' => 300
                ]

            ]
        ];
        return $this->request([
            'msg_type' => 'post',
            'content' => [
                'post' => [
                    'zh_cn' => [
                        'title' => $title,
                        'content' => $content
                    ]
                ]
            ],
            'at' => [
                'isAtAll' => $isAtAll
            ]
        ]);
    }

    /**
     * @param string $filePath
     * @return bool
     * @throws Exception
     */
    public function pushImage(string $filePath): bool
    {
        return $this->request([
            'msg_type' => 'image',
            'content' => [
                'image_key' => $this->uploadImage($filePath),
            ],
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
        $secret = data_get($robotConf, 'secret');
        $token = data_get($robotConf, 'token');

        $timestamp = time();
        $str = $timestamp . PHP_EOL . $secret;
        $sign = hash_hmac('sha256', '', $str, true);
        $sign = base64_encode($sign);

        return [
            'token' => $token,
            'timestamp' => $timestamp,
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
        $queryAuth = $this->queryAuth();
        $url = $this->url . $queryAuth['token'] ?? '';
        unset($queryAuth['token']);

        $client = $this->getClient();
        $json = array_merge($data, $queryAuth);
        // 发送请求
        try {
            $response = $client->request('POST', $url, [
                'json' => $json
            ]);
        } catch (GuzzleException $e) {
            Log::http('error', "请求Lark失败", ['url' => $url, 'requestBody' => $json, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);
        return ($content['code'] ?? -1) === 0 ? true : false;
    }

    /**
     * @param string $filePath
     * @return mixed
     * @throws Exception
     */
    public function uploadImage(string $filePath)
    {
        $client = $this->getClient();
        $url = 'https://open.larksuite.com/open-apis/image/v4/put/';
        $options = [
            'multipart' => [
                [
                    'name' => 'image_type',
                    'contents' => 'message',
                ],
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r'),
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken()
            ]
        ];

        // 发送请求
        try {
            $response = $client->request('POST', $url, $options);
        } catch (GuzzleException $e) {
            Log::http('error', "请求Lark失败", ['url' => $url, 'requestBody' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        if (($content['code'] ?? -1) === 0 && !empty($content['data']['image_key'])) {
            return $content['data']['image_key'];
        }
        throw new Exception($content['msg']);
    }

    protected function getAccessToken()
    {
        $cacheKey = 'lark_access_token';
        $accessToken = make(Cache::class)->get($cacheKey);
        if (!empty($accessToken)) {
            return $accessToken;
        }

        $client = $this->getClient();
        $url = 'https://open.larksuite.com/open-apis/auth/v3/tenant_access_token/internal';

        $options = [
            'json' => [
                'app_id' => config('lark.app_conf.app_id'),
                'app_secret' => config('lark.app_conf.app_secret')
            ],
        ];

        // 发送请求
        try {
            $response = $client->request('POST', $url, $options);
        } catch (GuzzleException $e) {
            Log::http('error', "请求Lark失败", ['url' => $url, 'requestBody' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        if (($content['code'] ?? -1) === 0 && !empty($content['tenant_access_token']) && !empty($content['expire'])) {
            make(Cache::class)->set('lark_access_token', $content['tenant_access_token'], $content['expire']);
            return $content['tenant_access_token'];
        } else {
            throw new Exception($content['msg']);
        }
    }
}