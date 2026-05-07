<?php
// 创建应用
namespace Tests;

use App\Services\Helper\Encrypt\Aes;
use App\Services\Helper\Helper;
use Laravel\Lumen\Application;

abstract class TestCase extends \Laravel\Lumen\Testing\TestCase
{
    protected $appid = '23dcfded08578239';
    protected $appkey = '883fa3095157cac7bb0f6bf5e891294e';
    protected $url = 'http://test-api.5gwifilink.com';
    /**
     * 创建应用
     * @return Application
     */
    public function createApplication()
    {
        return require __DIR__ . '/../bootstrap/app.php';
    }

    public function post($uri, array $data = [], array $headers = [], $isApi = false)
    {
        if($isApi){
            $response = Helper::request($this->url . $uri, 'POST', $this->format($data));
            var_dump($response);
            $content = json_decode($response['result'], true);
        }else{
            $response = parent::post($uri, $this->format($data), $headers);
            $content = json_decode($response->response->content(), true);
        }
        if(data_get($content, 'code') !== 0){
            return $content;
        }
        $result = $this->aes(data_get($content, 'data.response'), false);

        return json_decode($result, true);
    }

    protected function format($data)
    {
        $format = [
            'appid' => $this->appid,
            'timestamp' => (string)time(),
            'data' => $this->aes($data),
            'version' => '1.0.1',
        ];
        $format['sign'] = $this->sign($format);
        return $format;
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

    protected function aes($data, $isEncrypt = true)
    {
        if($isEncrypt){
            return Aes::encrypt(json_encode($data, 320), substr($this->appkey, 0, 16));
        }
        return Aes::decrypt(json_encode($data, 320), substr($this->appkey, 0, 16));

    }
}

