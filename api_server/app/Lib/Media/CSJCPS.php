<?php

namespace App\Lib\Media;

use App\Services\Helper\Helper;
use Exception;

class CSJCPS
{
    /**
     * CPS 接口地址
     * @var string
     */
    protected $url = 'https://ecom.pangolin-sdk-toutiao.com';

    /**
     * 应用ID
     * @var string
     */
    protected $appId;

    /**
     * SecurityKey
     * @var string
     */
    protected $secret;

    public function __construct(string $appId, string $secret)
    {
        $this->appId = strval($appId);
        $this->secret = strval($secret);
    }

    /**
     * 商品聚合页面
     * @return mixed
     * @throws Exception
     */
    public function link()
    {
        $result = $this->request('/aggregate/h5', [
            'material_id' => '20000'// 60000：秒杀 20000：百亿补贴
        ]);

        return $result['full_screen'];
    }

    /**
     * 请求
     * @param string $api
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    protected function request(string $api, array $data)
    {
        $data = [
            'app_id' => $this->appId,
            'timestamp' => time(),
            'req_id' => Helper::uniqueId(),
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ];
        $data['sign'] = $this->sign($data, $this->secret);

        $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = Helper::request($this->url . $api, 'POST', $data, [
            'Content-Type' => 'application/json'
        ]);

        $response = json_decode($result['result'], true);

        if (($response['code'] ?? -1) !== 0) {
            throw new Exception($response['desc'] ?? '响应异常');
        }

        return $response['data'];
    }

    /**
     * 签名
     * @param array $data
     * @param string $secret
     * @return string
     */
    protected function sign(array $data, string $secret)
    {
        $data = array_filter($data, 'strlen');
        ksort($data);
        return md5(urldecode(http_build_query($data)) . $secret);
    }
}