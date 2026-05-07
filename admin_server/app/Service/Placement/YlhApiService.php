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

class YlhApiService extends BaseApiService
{

    const API_URL = 'https://api.adnet.qq.com';
    const API_LINK_APP_QUERY = '/open/v1.1/medium/list';//查询应用列表
    const API_LINK_CREATE_PLACEMENT = '/open/v1.1/placement/add';//创建代码位

    const AD_SLOT_TYPE_NATIVE = 'FLOW_NORMAL_0';//代码位类型 - 信息流
    const AD_SLOT_TYPE_INTER = 'INSERTION_INSERTION';//代码位类型 - 插屏
    const AD_SLOT_TYPE_INTER_FULL = 'INSERTION_INSERTION';//代码位类型 - 全屏插屏
    const AD_SLOT_TYPE_SPLASH = 'FLASH_OTHER';//代码位类型 - 开屏
    const AD_SLOT_TYPE_REWORD_VIDEO = 'REWARDED_APP_OTHER';//代码位类型 - 激励视频

    public function __construct($account_id)
    {
        parent::__construct($account_id);
        if ($this->Account->platform != AppConst::PLATFORM_YLH) {
            throw new BusinessException(ErrorCode::PARAMS_ERROR, '主体账号信息错误！');
        }

    }

    public function queryApp()
    {
        $url = self::API_URL . self::API_LINK_APP_QUERY;
        $body = [
            'member_id' => $this->Conf['member_id'],
            'page' => 1,
            'page_size' => 100
        ];

        $response = $this->request($url, $body);

        $app_list = [];
        if ($response['code'] == 200) {
            $response_app_list = $response['data']['list'];
            foreach ($response_app_list as $row) {
                $data = [];
                $data['account_id'] = $this->Account->id;
                $data['app_id'] = $row['app_id'];
                $data['app_name'] = $row['medium_name'];
                $data['package_name'] = $row['package_name'];
                $data['status'] = in_array($row['status'], ['Approved']) ? 1 : 0;//Approved是正常状态 其他是停用状态
                $app_list[] = $data;
            }
        }
        return $app_list;


    }

    public function create(array $params)
    {

        $data = [
            'member_id' => $this->Conf['member_id'],
            'app_id' => (int)$params['app_id'],
            'placement_name' => $params['placement_data']['name'],
        ];
        $cpm = $params['placement_data']['price'];
        if ($cpm && $cpm > 0) {
            $data['price_strategy_type'] = 'TargetPrice';
            $data['ecpm_price'] = $cpm * 100;
        }

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
        $url = self::API_URL . self::API_LINK_CREATE_PLACEMENT;
        $response = $this->request($url, $body);
        if ($response['code'] == 200 || $response['code'] == 130317) {//code：130317 代码位创建成功，cpm未设置成功
            $placement_id = $response['data']['placement_id'];
            $response['data'] = [];
            $response['data']['placement_id'] = $placement_id;
            $response['data']['set_cpm_result'] = $response['code'] == 200 ? 'success' : 'fail';
            $response['code'] = 200;
        }

        return $response;
    }


    /**
     * 创建信息流
     */
    public function createNative()
    {
        return [
            'custom_position_scene_level3' => self::AD_SLOT_TYPE_NATIVE,
            'ad_pull_mode' => 'SDK',
            'render_type' => 'TEMPLATE',
            'ad_crt_type_list' => ['VIDEO', 'IMAGE'],
            'ad_crt_template_type' => ['TP_BT', 'TT_BP'],
        ];
    }

    /**
     * 创建插屏
     */
    public function createInter()
    {
        return [
            'custom_position_scene_level3' => self::AD_SLOT_TYPE_INTER,
            'ad_pull_mode' => 'SDK',
            'render_type' => 'TEMPLATE',
            'ad_crt_type_list' => ['VIDEO', 'IMAGE'],
            'ad_crt_template_type' => ['INLINE_VH'],
        ];
    }

    /**
     * 创建全屏插屏
     */
    public function createInterFull()
    {
        return [
            'custom_position_scene_level3' => self::AD_SLOT_TYPE_INTER_FULL,
            'ad_pull_mode' => 'SDK',
            'render_type' => 'TEMPLATE',
            'ad_crt_type_list' => ['VIDEO', 'IMAGE'],
            'ad_crt_template_type' => ['INLINE_FULL'],
        ];
    }

    /**
     * 创建开屏
     */
    public function createSplash()
    {
        return [
            'custom_position_scene_level3' => self::AD_SLOT_TYPE_SPLASH,
            'ad_pull_mode' => 'SDK',
            'flash_crt_type' => 'FLASH',
            'ad_crt_type_list' => ['FLASH_IMAGE_V', 'FLASH_IMAGE_H', 'FLASH_VIDEO_V', 'FLASH_VIDEO_H'],
        ];
    }

    /**
     * 创建激励视频
     */
    public function createRewordVideo()
    {
        return [
            'custom_position_scene_level3' => self::AD_SLOT_TYPE_REWORD_VIDEO,
            'ad_pull_mode' => 'SDK',
            'rewarded_video_crt_type' => 'VIDEO',
        ];
    }

    public function getToken(): string
    {
        $member_id = $this->Conf['member_id'];
        $secret = $this->Conf['secret'];
        $timestamp = time();
        $sign = sha1($member_id . $secret . $timestamp);
        return base64_encode($member_id . ',' . $timestamp . ',' . $sign);
    }


    public function request($url, $body)
    {
        $method = 'POST';
        $options = [
            'headers' => ['token' => $this->getToken()],
            'json' => $body
        ];
        Log::http('info', '发送优量汇请求', ['url' => $url, 'options' => $options]);
        $response = $this->sendRequest($method, $url, $options);
        if ($response->getStatusCode() == 200) {
            $result_contents = json_decode($response->getBody()->getContents(), true);
            Log::http('info', '优量汇请求结果', $result_contents);
            if (isset($result_contents['code']) && $result_contents['code'] == 0) {
                $result['code'] = 200;
                $result['msg'] = 'ok';
            } else {
                $result['code'] = $result_contents['code'] ?? 500;
                $result['msg'] = $result_contents['message'];
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