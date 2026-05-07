<?php

namespace Tests\V1;

use App\Services\Helper\Encrypt\Aes;
use App\Services\Helper\Helper;
use Tests\TestCase;

class ApiTest extends TestCase
{
    /**
     * 管家
     * @var string
     */
    // private $appid = '6d36e887a2d39f07';
    // private $appkey = 'bc250e98e7aade5c6d36e887a2d39f07';


    /**
     * 智连WiFi
     * @var string
     */
    // private $appid = 'ac831df31be88522';
    // private $appkey = '4509d9b297c61f71b95a29d58e3cbc16';

    /**
     * 助手
     * @var string
     */
//    private $appid = '31127d9980dfa4a3';
//    private $appkey = 'fc41ec53b7acb30ac0f256692e552116';


    /**
     * 椰子
     * @var string
     */
//    private $appid = '5e550171445aec8b';
//    private $appkey = '53254a6df0a41535fe58bd26d0de20ee';


    /**
     * 大师
     * @var string
//     */
//    private $appid = '21ce252d869c3376';
//    private $appkey = '06f05a9076bebdcc81f47326b79b5c56';

    /**
     * 随身
     * @var string
     */
    // private $appid = '8a93cf86ba2d3a90';
    // private $appkey = '3cfbbff57c48e47609861047af95bec6';


    /**
     * 随身
     * @var string
     */
//    private $appid = '22da3b215aa832ad';
//    private $appkey = '6fd167da4fd5a0a18a6e1360f652b208';


    /**
     * 解放大师
     * @var string
     */
//    private $appid = '28f147496430bd14';
//    private $appkey = 'a7fe463857b7688c5b4af76a3222fb16';

    // 万能手机清理
    protected $appid = '29adf3a078cbaf39';
    protected $appkey = '0fec5a2e03622fca39d3fe1e9a0d491d';

    /**
     * 请求地址
     * @var string
     */
    protected $url = 'http://test-api.5gwifilink.com';
//    private $url = 'http://nls-tools.io';

    /**
     * 是否远程
     * @var bool
     */
    private $remote = true;

    // 远程测试
    public function testConfig()
    {
        $data = [
            'channel' => '10',
            'ch'=>'br',
            'random_group_id'=>rand(0,99999),
            'from' => 'from_delay',
            'androidid' => '8ec573c5d3deceb4',
            'oaid' => '8c430dd09e53b935',
            'os_sdk_version' => '28',
            'wifilist' => [
                [
                    'ssid' => 'Huawei-Internet',
                    'bssid' => 'Huawei-Internet'
                ],
//                [
//                    'ssid' => 'OPPO',
//                    'bssid' => 'OPPO'
//                ]
            ],
            'imei' => '789',
            'ip' => '121.10.1.222',
            'dmac' => '000',
            'web_ua' => 'Mozilla/5.0 (Linux; Android 9; ASK-AL00x Build/HONORASK-AL00x; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/70.0.3538.64 Mobile Safari/537.36',
//            'model' => 'rk3399_mid',
//            'vendor' => 'Google',
            'vendor' => 'vivo22',
            'location' => [
                'privince' => '广州省',
                'city' => '东莞市'
            ],
//            'applist'=>[
//                'com.vivo.autotest.screen_record'
//            ],
            'net' => [
                'GSM' => [
//                        'mLac'=>33051,
//                        'mCid'=>164674308,
//                        'mPsc'=>0,
                ]
            ]
        ];

        dump($data);
        $format = [
            'appid' => $this->appid,
            'timestamp' => (string)time(),
            'data' => $this->aes($data),
            'version' => '1.0.8',
        ];
        $format['sign'] = $this->sign($format);
        dump($format);
        dump(http_build_query($format));
        $api = '/v1/version/config';
        if ($this->remote) {
            $result = Helper::request($this->url . $api, 'POST', $format);
            dump($result);
            $response = json_decode($result['result'], true);
            dump($response);
            $response = $this->des($response['data']['response']);
            dump($response);
        } else {
            // 发送请求
            $this->json('POST', $api, $format);
            // 响应数据
            $response = $this->response->getOriginalContent();
            dump($response);
            // 验证断言|响应数据
            $this->assertResponseOk();
            $this->seeJson([
                'code' => 0
            ]);
            $response = $this->des($response['data']['response']);
            dump($response);
            dump(json_decode($response, 320));
        }
    }

    /**
     * 广告打点
     */
    public function testEventAd()
    {
        $data = [
            'channel' => 9,
            'androidid' => '8ec573c5d3deceb4',
            'oaid' => 'fffb778c-fbdb-5bd6-fcdb-b99dedfe4afb',
            'imei' => '12345678',
            'ip' => '121.10.1.222',
            'model' => 'rk3399_mid',
            'vendor' => 'vivo',
            'uuid' => uniqid(),
            'event' => 'ad_request',
            'page' => 'splash',
            'key_type' => '123',
            'req_type' => '1234444',
            'type' => 'ssss',
            'count' => 2,
            'info' => '{"adSourceId":null,"code":"10003","mNetworkFirmId":null,"message":"{\"code\":10003,\"msg\":\"Invalid app\",\"data\":\"\"}","networkPlacementId":null,"placement":null,"type":null}',
//            'info' => json_encode([
//                'type' => 2222222,
//                'placementId' => '33333',
//                'code' => 'ddddddd',
//                'message' => 'qrrrbd',
//                'aa' => ['bbbdd', 'dd' => '234']
//            ])
        ];
        dump($data);
        $format = [
            'appid' => $this->appid,
            'timestamp' => (string)time(),
            'data' => $this->aes($data),
            'version' => '1.01.5',
        ];
        $format['sign'] = $this->sign($format);
        dump($format);
        dump(http_build_query($format));
        $api = '/v1/event/ad';
        if ($this->remote) {
            $result = Helper::request($this->url . $api, 'POST', $format);
            dump($result);
            $response = json_decode($result['result'], true);
            dump($response);
            $response = $this->des($response['data']['response']);
            dump($response);
        } else {
            dump('local');
            // 发送请求
            $this->json('POST', $api, $format);
            // 响应数据
            $response = $this->response->getOriginalContent();
            dump($response);
            // 验证断言|响应数据
            $this->assertResponseOk();
            $this->seeJson([
                'code' => 0
            ]);
            $response = $this->des($response['data']['response']);
            dump($response);
        }
    }

    /**
     * 版本更新
     */
    public function testUpdate()
    {
        $data = [
            'channel' => 1001,
            'androidid' => '6332f148a6208f51',
            'oaid' => 'fffb778c-fbdb-5bd6-fcdb-b99dedfe4afb',
            'imei' => '12345678',
            'ip' => '121.10.1.222',
            'model' => 'rk3399_mid',
            'vendor' => 'vivo',
            'uuid' => uniqid(),
            'os_sdk_version' => '12',
            'time' => '20001',
        ];
        $format = [
            'appid' => $this->appid,
            'timestamp' => (string)time(),
            'data' => $this->aes($data),
            'version' => '1.0.1',
        ];
        $format['sign'] = $this->sign($format);
        dump($format);
        $api = '/v1/version/update';
        if (!$this->remote) {
            $result = Helper::request($this->url . $api, 'POST', $format);
            dump($result);
            $response = json_decode($result['result'], true);
            dump($response);
            $response = $this->des($response['data']['response']);
            dump($response);
        } else {
            // 发送请求
            $this->json('POST', $api, $format);
            // 响应数据
            $response = $this->response->getOriginalContent();
            dump($response);
            // 验证断言|响应数据
            $this->assertResponseOk();
            $this->seeJson([
                'code' => 0
            ]);
            $response = $this->des($response['data']['response']);
            dump($response);
        }
    }

    /**
     * 版本更新
     */
    public function testInfo()
    {
        $data = [
            'channel' => 1001,
            'androidid' => '6332f148a6208f51',
            'oaid' => 'fffb778c-fbdb-5bd6-fcdb-b99dedfe4afb',
            'imei' => '12345678',
            'ip' => '121.10.1.222',
            'random_group_id' => 589,
            'model' => 'rk3399_mid',
            'vendor' => 'vivo',
            'uuid' => uniqid(),
            'os_sdk_version' => '12',
            'time' => '20001',
        ];
        $format = [
            'appid' => $this->appid,
            'timestamp' => (string)time(),
            'data' => $this->aes($data),
            'version' => '1.0.1',
        ];
        $format['sign'] = $this->sign($format);
        dump($format);
        $api = '/v1/version/config';
        if ($this->remote) {
            $result = Helper::request($this->url . $api, 'POST', $format);
            dump($result);
            $response = json_decode($result['result'], true);
            dump($response);
            $response = $this->des($response['data']['response']);
            dump($response);
        } else {
            // 发送请求
            $this->json('POST', $api, $format);
            // 响应数据
            $response = $this->response->getOriginalContent();
            dump($response);
            // 验证断言|响应数据
            $this->assertResponseOk();
            $this->seeJson([
                'code' => 0
            ]);
            $response = $this->des($response['data']['response']);
            dump($response);
        }
    }

    /**
     * 保活打点
     */
    public function testActive()
    {
        $data = [
            'channel' => 22,
            'androidid' => '6332f148a6208f51',
            'oaid' => 'fffb778c-fbdb-5bd6-fcdb-b99dedfe4afb',
            'imei' => '12345678',
            'ip' => '121.10.1.222',
            'model' => 'rk3399_mid',
            'vendor' => 'vivo',
            'uuid' => uniqid(),
            'os_sdk_version' => '12',
            'time' => '20001',
        ];
        $format = [
            'appid' => $this->appid,
            'timestamp' => (string)time(),
            'data' => $this->aes($data),
            'version' => '1.0.5',
        ];
        $format['sign'] = $this->sign($format);
        dump($format);
        $api = '/v1/event/active';
        if ($this->remote) {
            $result = Helper::request($this->url . $api, 'POST', $format);
            dump($result);
            $response = json_decode($result['result'], true);
            dump($response);
            $response = $this->des($response['data']['response']);
            dump($response);
        } else {
            // 发送请求
            $this->json('POST', $api, $format);
            // 响应数据
            $response = $this->response->getOriginalContent();
            dump($response);
            // 验证断言|响应数据
            $this->assertResponseOk();
            $this->seeJson([
                'code' => 0
            ]);
            $response = $this->des($response['data']['response']);
            dump($response);
        }
    }

    private function des($data)
    {
        return Aes::decrypt($data, substr($this->appkey, 0, 16));
    }

    protected function sign($param)
    {
        // 过滤签名
        unset($param['sign']);
        // 过滤空值
        $param = array_filter($param, 'strlen');
        // 排序
        ksort($param);
        // 追加秘钥
        $param['key'] = substr($this->appkey, -16);
        // 生成签名
        return strtoupper(md5(urldecode(http_build_query($param))));
    }
}
