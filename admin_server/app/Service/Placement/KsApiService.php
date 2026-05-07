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

class KsApiService extends BaseApiService
{

    const API_URL = 'https://u.kuaishou.com';
    const API_LINK_APP_QUERY = '/api/app/get';//查询应用列表
    const API_LINK_CREATE_PLACEMENT = '/api/position/add';//创建代码位
    const API_LINK_SET_ECPM = '/api/position/modCpmFloor';//设置ECPM

    const AD_SLOT_TYPE_NATIVE = 1;//代码位类型 - 信息流
    const AD_SLOT_TYPE_INTER = 13;//代码位类型 - 插屏
    const AD_SLOT_TYPE_FULL_VIDEO = 3;//代码位类型 - 全屏视频
    const AD_SLOT_TYPE_SPLASH = 4;//代码位类型 - 开屏
    const AD_SLOT_TYPE_REWORD_VIDEO = 2;//代码位类型 - 激励视频

    public $time;

    public function __construct($account_id)
    {
        parent::__construct($account_id);
        if ($this->Account->platform != AppConst::PLATFORM_KS) {
            throw new BusinessException(ErrorCode::PARAMS_ERROR, '主体账号信息错误！');
        }
        $this->time = time();
    }

    public function queryApp(): array
    {
        $url = $this->buildUrl(self::API_LINK_APP_QUERY);
        $body = [
            'page' => 1,
            'pageSize' => 500
        ];
        $response = $this->request($url, $body);
        $app_list = [];
        if ($response['code'] == 200) {
            $response_app_list = $response['data'];
            foreach ($response_app_list as $row) {
                $data = [];
                $data['account_id'] = $this->Account->id;
                $data['app_id'] = $row['app_id'];
                $data['app_name'] = $row['name'];
                $data['package_name'] = $row['package_name'];
                $data['status'] = in_array($row['audit_status'], [3]) ? 1 : 0;//3和8是正常状态 其他是停用状态
                $app_list[] = $data;
            }
        }
        return $app_list;
    }

    public function create($params)
    {
        $this->time += $params['placement_data']['index'];
        $url = $this->buildUrl(self::API_LINK_CREATE_PLACEMENT);
        $data = [
            'appId' => $params['app_id'],
            'name' => $params['placement_data']['name'],
        ];

        $ad_type_params = [];
        switch ($params['placement_data']['type']) {
            case AppConst::AD_TYPE_NATIVE:
                $ad_type_params = $this->createNative();
                break;
            case AppConst::AD_TYPE_INTER:
                $ad_type_params = $this->createInter();
                break;
            case AppConst::AD_TYPE_INTER_FULL:
                $ad_type_params = $this->createInterFull();
                break;
            case AppConst::AD_TYPE_SPLASH:
                $ad_type_params = $this->createSplash();
                break;
            case AppConst::AD_TYPE_REWORD_VIDEO:
                $ad_type_params = $this->createRewordVideo();
                break;
        }

        $body = array_merge($data, $ad_type_params);

        $response = $this->request($url, $body);
        if ($response['code'] == 200) {
            $placement_id = $response['data'];
            $response['data'] = [];
            $response['data']['placement_id'] = $placement_id;
            $response['data']['set_cpm_result'] = 'success';
            $placement_price = $params['placement_data']['price'];
            if ($placement_price > 0) {
                $response_set_cpm = $this->setCpm($params['app_id'], $placement_id, $placement_price);
                if ($response_set_cpm['code'] != 200) {
                    $response['data']['set_cpm_result'] = 'fail';
                }
            }
        }

        return $response;

    }


    /**
     * 设置保价cpm
     * @param $appid
     * @param $placement_id
     * @param $cpm
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function setCpm($appid, $placement_id, $cpm)
    {
        $url = $this->buildUrl(self::API_LINK_SET_ECPM);
        $body = [
            'appId' => $appid,
            'positionId' => $placement_id,
            'cpmFloor' => $cpm,
        ];
        return $this->request($url, $body);
    }

    /**
     * 创建信息流
     */
    public function createNative()
    {
        return [
            'adStyle' => self::AD_SLOT_TYPE_NATIVE,
            'renderType' => 2,//渲染⽅式 2（模版渲染），1（⾃渲染）
            'materialTypeList' => [2, 6],//素材类型 1-竖版视频,2-横版视频,5-竖版图片,6-横版图片
            'multiTemplateParams' => [['templateId' => 1], ['templateId' => 2], ['templateId' => 3], ['templateId' => 4], ['templateId' => 5]]
        ];
    }

    /**
     * 创建插屏
     */
    public function createInter()
    {
        return [
            'adStyle' => self::AD_SLOT_TYPE_INTER,
            'renderType' => 2,//渲染⽅式 2（模版渲染），1（⾃渲染）
            'materialTypeList' => [1, 2, 5, 6],//素材类型 1-竖版视频,2-横版视频,5-竖版图片,6-横版图片
            'templateId' => 9,//渲染模版ID
        ];
    }

    /**
     * 创建全屏插屏
     */
    public function createInterFull()
    {
        return $this->createFullVideo();
    }

    /**
     * 创建全屏视频
     */
    public function createFullVideo()
    {
        return [
            'adStyle' => self::AD_SLOT_TYPE_FULL_VIDEO,
            'renderType' => 1,//渲染⽅式 2（模版渲染），1（⾃渲染）
            'materialTypeList' => [1],//素材类型 1-竖屏,2-横屏
        ];
    }

    /**
     * 创建开屏
     */
    public function createSplash()
    {
        return [
            'adStyle' => self::AD_SLOT_TYPE_SPLASH,
            'renderType' => 2,//渲染⽅式 2（模版渲染），1（⾃渲染）
            'materialTypeList' => [1, 3],//素材类型 1-竖版视频, 3-单图
            'templateId' => 1000,//渲染模版ID
            'skipAdMode' => 0,//跳过按钮
            'countdownShow' => 1,//跳过按钮是否显示 倒计时
            'voice' => 1,//播放声音 1-静音
        ];
    }

    /**
     * 创建激励视频
     */
    public function createRewordVideo()
    {
        return [
            'adStyle' => self::AD_SLOT_TYPE_REWORD_VIDEO,
            'renderType' => 1,//渲染⽅式 2（模版渲染），1（⾃渲染）
            'materialTypeList' => [1],//视频播放方向 1-竖版视频,2-横版视频
            'rewardedType' => 11,//奖励名称 1-虚拟金币, 2-积分,3-生命, 4-体力值,5-通关机会, 6-新关卡机会, "7-阅读币,8-新章节（小说类）, 9-观影币, 10-观看机会, 11-其他
            'rewardedNum' => 0,//奖励数量
            'callbackStatus' => 0,//奖励发放设置 0-关闭（无需服务端创建）1-开启（需要服务端创建）
        ];
    }


    /**
     * 构造请求url
     * @param $api_link
     * @param array $params
     * @return string
     */
    public function buildUrl($api_link, array $params = []): string
    {
        $ak = $this->Conf['ak'];
        $sk = $this->Conf['sk'];
        $common_params = [
            'ak' => $ak,
            'timestamp' => $this->time
        ];

        $params = array_merge($params, $common_params);

        $sign = $this->getSign($api_link, $sk, $params);
        $params['sign'] = $sign;
        $params = http_build_query($params);
        return self::API_URL . $api_link . '?' . $params;
    }

    public function getSign($api_link, $sk, $params): string
    {
        $params['sk'] = $sk;
        ksort($params);
        $params = http_build_query($params);
        $str = $api_link . '?' . $params;
        return md5($str);
    }

    /**
     * @param $url
     * @param $body
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($url, $body): array
    {
        $method = 'POST';
        $options = ['json' => $body];
        Log::http('info', '发送快手请求', ['url' => $url, 'options' => $options]);
        $response = $this->sendRequest($method, $url, $options);
        if ($response->getStatusCode() == 200) {
            $result_contents = json_decode($response->getBody()->getContents(), true);
            Log::http('info', '快手请求结果', $result_contents);
            if (isset($result_contents['result']) && $result_contents['result'] == 1) {
                $result['code'] = 200;
                $result['msg'] = 'ok';
            } else {
                $result['code'] = $result_contents['result'] ?? 500;
                $result['msg'] = $result_contents['error_msg'];
            }
            $result['data'] = $result_contents['data'];
        } else {
            $result['code'] = $response->getStatusCode();
            $result['msg'] = $response->getReasonPhrase();
            $result['data'] = [];
        }

        return $result;
    }
}