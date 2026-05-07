<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/17
 */

namespace App\Service\Placement;

use App\Constants\ErrorCode;
use App\Constants\AppConst;
use App\Exception\BusinessException;
use App\Utils\Log;

class ToponApiService extends BaseApiService
{

    const API_URL = 'https://openapi.toponad.com';
    const API_LINK_APP_QUERY = '/v1/apps';//查询应用列表
    const API_LINK_BIND_PLACEMENT = '/v1/set_units';//绑定代码位ID


    const ADSOURCE_TOKEN_PLACEMENT_FIELD = [
        'ks' => 'position_id',//快手代码位ID字段
        'csj' => 'slot_id',//穿山甲代码位ID字段
        'ylh' => 'unit_id',//优量汇代码位ID字段
        'hw' => 'ad_id'//华为
    ];

    const ADSOURCE_TOKEN = [
        'ks' => [//快手
            'native' => [//信息流
                'unit_type' => '0',//0- 信息流广告 （默认）， 1- Draw视频广告
                'layout_type' => '1',//0-自渲染 （默认）, 1-模板
                'video_sound' => '0'//0-无声音 （默认）,1-有声音
            ],
            'inter' => [//插屏
                'orientation' => '1',//1-竖屏(默认), 2-横屏
                'video_muted' => '0',//0-否，有声音(默认), 1-是，静音
                'is_video' => '0'//0- 否(插屏广告）1-是（全屏视频广告，默认）
            ],
            'inter_full' => [//全屏插屏
                'orientation' => '1',//1-竖屏(默认), 2-横屏
                'video_muted' => '0',//0-否，有声音(默认), 1-是，静音
                'is_video' => '0'//0- 否(插屏广告）1-是（全屏视频广告，默认）
            ],
            'full_video' => [//全屏视屏
                'orientation' => '1',//1-竖屏(默认), 2-横屏
                'video_muted' => '0',//0-否，有声音(默认), 1-是，静音
                'is_video' => '1'//0- 否(插屏广告）1-是（全屏视频广告，默认）
            ],
            'splash' => [//开屏
                'zoomoutad_sw' => '1',//1-不使用开屏V+功能（默认）, 2-使用开展V+功能
            ],
            'reword_video' => [//激励视屏
                'orientation' => '1',//1-竖屏(默认), 2-横屏
                'video_muted' => '0',//0-否，有声音(默认), 1-是，静音
            ]
        ],
        'csj' => [
            'native' => [//信息流
                'is_video' => '0',//0-信息源广告 （默认）, 1-Draw信息流广告, 2-Banner广告（原生类型=自渲染）, 3-插屏广告（原生类型=自渲染）,4-视频贴片广告
                'layout_type' => '0',//0-模板渲染 （默认）,1-自渲染
                'media_size' => '1'//（layout_type = 1必填）：1 - 690x388px （默认）, 2 - 228x150px
            ],
            'inter' => [//插屏
                'is_video' => '2',//0-插屏广告, 1-全屏视频广告 （默认）2- 新插屏广告
                'layout_type' => '1',//
                'size' => '2:3',//1:1, 3:2, 2:3 （默认）
                'personalized_template' => '1'//0-非个性化模板, 1-个性化模板（默认）
            ],
            'inter_full' => [//全屏插屏
                'is_video' => '2',//0-插屏广告, 1-全屏视频广告 （默认）2- 新插屏广告
                'layout_type' => '1',//
                'size' => '1:1',//1:1, 3:2, 2:3 （默认）
                'personalized_template' => '1'//0-非个性化模板, 1-个性化模板（默认）
            ],
            'full_video' => [//全屏视屏
                'is_video' => '2',//0-插屏广告, 1-全屏视频广告 （默认）2- 新插屏广告
                'layout_type' => '1',//
                'size' => '1:1',//1:1, 3:2, 2:3 （默认）
                'personalized_template' => '1'//0-非个性化模板, 1-个性化模板（默认）
            ],
            'splash' => [//开屏
                'personalized_template' => '0',//0-非个性化模板, 1-个性化模板（默认）
                'zoomoutad_sw' => '1'//1-否，不设置开展点睛的icon （默认）；2-是，开展点睛的icon设置成Yes
            ],
            'reword_video' => [//激励视屏
                'personalized_template' => '1',//0-非个性化模板, 1-个性化模板（默认）
            ]
        ],
        'ylh' => [
            'native' => [//信息流
                'unit_version' => '2',//1- 1.0版本，2- 2.0版本
                'unit_type' => '1',//1-平台模板渲染，2-开发者自渲染 ，3-视频贴片广告（模板渲染），4-视频贴片广告（自渲染）
                'video_muted' => '1',//(可选)1-Yes（静音，默认）， 0-No（有声音)
                'video_autoplay' => '1',//(可选)0-WiFi 网络自动播放，4G 网络手动点击播放; 1-总是自动播放（ 默认）; 2-点击播放
                'video_duration' => '0',//(可选)视频最大时长,可选时长（5~61秒），0代表不限时长，不传时默认60秒
            ],
            'inter' => [//插屏
                'unit_version' => '2',//1- 1.0版本，2- 2.0版本
                'is_fullscreen' => '0',//0-No,1-Yes （默认）
                'video_muted' => '0',//(可选)1-Yes（静音，默认）,0-No（有声音）
                'video_autoplay' => '1',//(可选)0-（WiFi 网络自动播放，4G 网络手动点击播放）, 1-（WiFi 和4G 网络都自动播放，默认值为 1 ）,2-点击播放
                'video_duration' => '0',//(可选)视频最大时长,可选时长（5~61秒），0代表不限时长，不传时默认60秒
                'personalized_template' => '0',//0-否（默认), 1- 平台模板2.0
            ],
            'inter_full' => [//全屏插屏
                'unit_version' => '2',//1- 1.0版本，2- 2.0版本
                'is_fullscreen' => '1',//0-No,1-Yes （默认）
                'video_muted' => '0',//(可选)1-Yes（静音，默认）,0-No（有声音）
                'video_autoplay' => '1',//(可选)0-（WiFi 网络自动播放，4G 网络手动点击播放）, 1-（WiFi 和4G 网络都自动播放，默认值为 1 ）,2-点击播放
                'video_duration' => '0',//(可选)视频最大时长,可选时长（5~61秒），0代表不限时长，不传时默认60秒
                'personalized_template' => '0',//0-否（默认), 1- 平台模板2.0
            ],
            'full_video' => [//全屏视屏
                'unit_version' => '2',//1- 1.0版本，2- 2.0版本
                'is_fullscreen' => '1',//0-No,1-Yes （默认）
                'video_muted' => '0',//(可选)1-Yes（静音，默认）,0-No（有声音）
                'video_autoplay' => '1',//(可选)0-（WiFi 网络自动播放，4G 网络手动点击播放）, 1-（WiFi 和4G 网络都自动播放，默认值为 1 ）,2-点击播放
                'video_duration' => '0',//(可选)视频最大时长,可选时长（5~61秒），0代表不限时长，不传时默认60秒
                'personalized_template' => '0',//0-否（默认), 1- 平台模板2.0
            ],
            'splash' => [//开屏
                'zoomoutad_sw' => '1',//1-不使用开屏V+功能（默认）, 2-使用开展V+功能
            ],
            'reword_video' => [//激励视屏
                'personalized_template' => '0',//0-SDK原生渲染（默认), 1- 平台模板2.0
                'video_muted' => '0',//1-Yes（静音, 默认）, 0-No（有声音）
            ]
        ],
        'hw' => [
            'native' => [
                'media_ratio' => '0',// 0-未指定(默认),1-任意尺寸,2-横屏,3-竖屏,4-正方形
                'orientation' => '0',// (设置图片方向)0-任意方向(默认), 1-PORTRAIT, 2-LANDSCAPE
                'video_muted' => '0'// 0-静音(默认), 1- 不静音
            ],
            'splash' => [// 暂不支持
                'orientation' => '1'//1-竖屏（默认)2-横屏
            ]
            // 其他类型无特殊参数
        ]
    ];

    public function __construct($account_id)
    {
        parent::__construct($account_id);
        if ($this->Account->platform != AppConst::PLATFORM_TOPON) {
            throw new BusinessException(ErrorCode::PARAMS_ERROR, '主体账号信息错误！');
        }
    }

    public function queryApp()
    {
        $method = 'POST';
        $body = [
            'start' => 0,
            'limit' => 100
        ];

        $response = $this->request(self::API_LINK_APP_QUERY, $method, $body);
        $app_list = [];
        if ($response['code'] == 200) {
            $response_app_list = $response['data'];
            foreach ($response_app_list as $row) {
                $data = [];
                $data['account_id'] = $this->Account->id;
                $data['app_id'] = $row['app_id'];
                $data['app_name'] = $row['app_name'];
                $data['package_name'] = $row['package_name'];
                $data['status'] = 1;//接口未返回状态 默认都是正常状态
                $app_list[] = $data;
            }
        }
        return $app_list;
    }

    public function create(array $params)
    {
        // TODO: Implement create() method.
    }

    public function bind(array $params)
    {

        $platform = $params['placement_data']['platform'];
        $placement_name = $params['placement_data']['placement_name'];
        $placement_type = $params['placement_data']['placement_type'];
        $placement_id = $params['placement_data']['placement_id'];
        $topon_id = $params['placement_data']['topon_id'];
        $topon_price = $params['placement_data']['topon_price'];
        $units = [
            [
                'network_id' => $this->Conf['network_' . $platform],
                'adsource_name' => $placement_name,
                'adsource_token' => array_merge(self::ADSOURCE_TOKEN[$platform][$placement_type] ?? [], [self::ADSOURCE_TOKEN_PLACEMENT_FIELD[$platform] => $placement_id]),
                'placement_id' => $topon_id,//toponID
                'default_ecpm' => (string)$topon_price
            ]
        ];
        $body = [
            'count' => count($units),
            'units' => $units
        ];

        $method = 'POST';
        $response = $this->request(self::API_LINK_BIND_PLACEMENT, $method, $body);
        if ($response['code'] == 200 && isset($response['data'][0]['errors'])) {
            $response['code'] = 500;
            $response['msg'] = $response['data'][0]['errors'];
        }

        return $response;
    }


    /**
     * @param $api_link
     * @param $headers
     * @param $method
     * @param $body_data
     * @return string
     */
    public function getSign($api_link, $headers, $method, $body_data): string
    {
        $header_str = '';
        foreach ($headers as $key => $value) {
            if (empty($header_str)) {
                $header_str = "$key:$value";
            } else {
                $header_str = "$header_str\n$key:$value";
            }
        }

        if ($method == "GET") {
            $body_str = "";
        } else {
            $body_str = json_encode($body_data);
        }

        $body_md5 = strtoupper(md5($body_str));
        $content_type = 'application/json';
        $sign_str = $method . "\n"
            . $body_md5 . "\n"
            . $content_type . "\n"
            . $header_str . "\n"
            . $api_link;

        return strtoupper(md5($sign_str));
    }


    public function request($api_link, $method, $body)
    {
        $timestamp = $this->getMillisecond();
        $url = self::API_URL . $api_link;
        $headers = [];
        $headers['X-Up-Key'] = $this->Conf['publisher_key'];
        $headers['X-Up-Timestamp'] = $timestamp;
        $headers['X-Up-Signature'] = $this->getSign($api_link, $headers, $method, $body);
        $options = [
            'http_errors' => false,
            'headers' => $headers,
            'json' => $body
        ];

        Log::http('info', '发送Topon请求', ['url' => $url, 'options' => $options]);
        $response = $this->sendRequest($method, $url, $options);
        if ($response->getStatusCode() == 200) {
            $result_contents = json_decode($response->getBody()->getContents(), true);
            Log::http('info', 'Topon请求结果', $result_contents);
            $result['code'] = 200;
            $result['msg'] = 'ok';
            $result['data'] = $result_contents;
        } else {
            $result['code'] = $response->getStatusCode();
            $result['msg'] = $response->getReasonPhrase();
            $result['data'] = [];
        }

        return $result;
    }

    function getMillisecond()
    {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }
}