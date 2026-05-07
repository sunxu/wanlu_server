<?php

namespace Tests\V1;

use App\Http\Controllers\V1\Paid\PayController;
use App\Services\Helper\Encrypt\Aes;
use App\Services\Pay\WechatService;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    protected $appid = '088f335bc46a1aa8';
    protected $appkey = '1a9c7ec278bbec8e53c6d79f1cf25c4c';
    protected $url = 'https://api.5gwifilink.com';

    public function testWeather()
    {
        $data = [
            'list' => [
                ['lonlat' => '116.488618,39.978471']
            ],
            'is_yesterday' => true
        ];
        $response = $this->post('/v1/weather/alarm', $data);
        var_dump($response->response->content());
        die;
    }

    public function testWechat()
    {
        $data = ['android_id'=>'a706660e75b33ba4', 'goods_no' => '426500265126961152'];
        $result = $this->post('/v1/paid/app/wechat', $data, [], true);
        var_dump(json_encode($result));
        dd();
    }


}