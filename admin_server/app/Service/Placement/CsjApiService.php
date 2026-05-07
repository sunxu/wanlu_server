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

class CsjApiService extends BaseApiService
{

    const API_URL = 'https://open-api.csjplatform.com';
    const API_LINK_APP_QUERY = '/union/media/open_api/site/query';//查询应用列表
    const API_LINK_CREATE_PLACEMENT = '/union/media/open_api/code/create';//创建代码位

    const API_VERSION = '1.0';//接口版本号

    const AD_SLOT_TYPE_NATIVE = 1;//代码位类型 - 信息流
    const AD_SLOT_TYPE_INTER = 9;//代码位类型 - 插屏
    const AD_SLOT_TYPE_INTER_FULL = 9;//代码位类型 - 全屏插屏
    const AD_SLOT_TYPE_SPLASH = 3;//代码位类型 - 开屏
    const AD_SLOT_TYPE_REWORD_VIDEO = 5;//代码位类型 - 激励视频

    public function __construct($account_id)
    {
        parent::__construct($account_id);
        if ($this->Account->platform != AppConst::PLATFORM_CSJ) {
            throw new BusinessException(ErrorCode::PARAMS_ERROR, '主体账号信息错误！');
        }
    }

    public function queryApp()
    {
        $data = [
            'page' => 1,
            'page_size' => 500,
        ];

        $body = array_merge($this->commonParams(), $data);
        $url = self::API_URL . self::API_LINK_APP_QUERY;
        $response = $this->request($url, $body);
        $app_list = [];
        if ($response['code'] == 200) {
            $response_app_list = $response['data']['app_list'];
            foreach ($response_app_list as $row) {
                $data = [];
                $data['account_id'] = $this->Account->id;
                $data['app_id'] = $row['app_id'];
                $data['app_name'] = $row['app_name'];
                $data['package_name'] = $row['package_name'];
                $data['status'] = in_array($row['status'], [2]) ? 1 : 0;//2和6是正常状态 其他是停用状态
                $app_list[] = $data;
            }
        }
        return $app_list;

    }

    public function create(array $params)
    {
        $data = [
            'ad_slot_name' => $params['placement_data']['name'],
            'app_id' => (int)$params['app_id']
        ];
        $cpm = $params['placement_data']['price'];
        if ($cpm && $cpm > 0) {
            $data['cpm'] = (string)$cpm;//cpm设置 必须为字符串支持小数
        }
        $common_params = array_merge($this->commonParams(), $data);

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

        $body = array_merge($common_params, $ad_type_params);
        // gm判断
        if (strstr($body['ad_slot_name'], 'gm')) {
            $body['use_mediation'] = '1';
        }
        $url = self::API_URL . self::API_LINK_CREATE_PLACEMENT;

        $response = $this->request($url, $body);
        if ($response['code'] == 200) {
            $placement_id = $response['data']['ad_slot_id'];
            $response['data'] = [];
            $response['data']['placement_id'] = $placement_id;
            $response['data']['set_cpm_result'] = 'success';
        }

        return $response;
    }


    /**
     * 创建信息流
     */
    public function createNative()
    {
        return [
            'ad_slot_type' => self::AD_SLOT_TYPE_NATIVE,
            'render_type' => 1,//渲染⽅式 1（模版渲染），2（⾃渲染）
            'accept_material_type' => 3,//代码位可接受素材类型 1-仅图⽚，2-仅视频，3-图⽚+视频，建议选3；
            'template_layouts' => [1, 2],//优选模版 1:上⽂下图 2:上图下⽂ 3:⽂字浮层 4:竖版 5:左图右⽂ 6:左⽂右图 7:三图
            'use_mediation' => '0',//是否使⽤穿⼭甲聚合 1）"0" 不使⽤， "1" 使⽤聚合 注：只有应⽤加⼊聚合的 时候才可以设置代码位为使⽤聚合
            'bidding_type' => 0,//竞价类型 0（标准）、1（服务端竞价）
        ];
    }

    /**
     * 创建插屏
     */
    public function createInter()
    {
        return [
            'ad_slot_type' => self::AD_SLOT_TYPE_INTER,
            'render_type' => 1,//渲染类型 1（模板渲染）、3（⾮原⽣）
            'ad_rollout_size' => 2,//⼴告铺开⼤⼩ 1是全屏，2是半屏
            'accept_material_type' => 3,//⼴告素材类型 1是仅图⽚；2是仅视频；3是图⽚+视频
            'orientation' => 1,//视频播放⽅向/屏幕⽅向 1是竖版；2是横版
            'skip_duration' => 0, //n秒后显⽰跳过按钮 全屏数值范围为5-15s；插屏数值范围为0-15s
            'use_mediation' => '0',//是否使⽤穿⼭甲聚合 1）"0" 不使⽤， "1" 使⽤聚合 注：只有应⽤加⼊聚合的 时候才可以设置代码位为使⽤聚合
            'bidding_type' => 0,//竞价类型 0（标准）、1（服务端竞价）
        ];
    }

    /**
     * 创建全屏插屏
     */
    public function createInterFull()
    {
        return [
            'ad_slot_type' => self::AD_SLOT_TYPE_INTER_FULL,
            'render_type' => 1,//渲染类型 1（模板渲染）、3（⾮原⽣）
            'ad_rollout_size' => 1,//⼴告铺开⼤⼩ 1是全屏，2是半屏
            'accept_material_type' => 3,//⼴告素材类型 1是仅图⽚；2是仅视频；3是图⽚+视频
            'orientation' => 1,//视频播放⽅向/屏幕⽅向 1是竖版；2是横版
            'skip_duration' => 5, //n秒后显⽰跳过按钮 全屏数值范围为5-15s；插屏数值范围为0-15s
            'use_mediation' => '0',//是否使⽤穿⼭甲聚合 1）"0" 不使⽤， "1" 使⽤聚合 注：只有应⽤加⼊聚合的 时候才可以设置代码位为使⽤聚合
            'bidding_type' => 0,//竞价类型 0（标准）、1（服务端竞价）
        ];

    }


    /**
     * 创建开屏
     */
    public function createSplash()
    {
        return [
            'ad_slot_type' => self::AD_SLOT_TYPE_SPLASH,
            'render_type' => 3,//渲染⽅式 1（模板渲染）、3（⾮原⽣）
            'use_icon' => 2,//是否使⽤点睛样式 ⾮原⽣时⽣效  1（使⽤）2（不使⽤），不传值时默认使⽤
            'use_mediation' => '0',//是否使⽤穿⼭甲聚合 1）"0" 不使⽤， "1" 使⽤聚合 注：只有应⽤加⼊聚合的 时候才可以设置代码位为使⽤聚合
            'bidding_type' => 0,//竞价类型 0（标准）、1（服务端竞价）
        ];
    }


    /**
     * 创建激励视频
     */
    public function createRewordVideo()
    {
        return [
            'ad_slot_type' => self::AD_SLOT_TYPE_REWORD_VIDEO,
            'render_type' => 1,//渲染⽅式 1（模板渲染）、3（⾮原⽣）
            'orientation' => 1,//视频播放⽅向 1（垂直）、2（⽔平）
            'reward_is_callback' => 0,//奖励发放设置 0（⽆需服务器判断）、1（需要服务器判断）
            'use_mediation' => '0',//是否使⽤穿⼭甲聚合 1）"0" 不使⽤， "1" 使⽤聚合 注：只有应⽤加⼊聚合的 时候才可以设置代码位为使⽤聚合
            'bidding_type' => 0,//竞价类型 0（标准）、1（服务端竞价）
        ];
    }


    public function request($url, $body)
    {
        $method = 'POST';
        $options = ['json' => $body];
        Log::http('info', '发送穿山甲请求', ['url' => $url, 'options' => $options]);
        $response = $this->sendRequest($method, $url, $options);
        if ($response->getStatusCode() == 200) {
            $result_contents = json_decode($response->getBody()->getContents(), true);
            Log::http('info', '穿山甲请求结果', $result_contents);
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

    /**
     * 公共参数
     * @return array
     */
    public function commonParams(): array
    {
        $timestamp = time();
        $nonce = $this->getNonce();//长度与timestamp保持一致，否则可能会导致签名失败
        $user_id = $this->Conf['user_id'];
        $role_id = $this->Conf['role_id'];
        $secret = $this->Conf['secret'];
        $sign = $this->getSign($timestamp, $nonce, $secret);
        $version = self::API_VERSION;
        return [
            'user_id' => $user_id,
            'role_id' => $role_id,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'sign' => $sign,
            'version' => $version,
        ];
    }


    /**
     * @param $time
     * @param $nonce
     * @param $secret
     * @return string
     */
    public function getSign($time, $nonce, $secret): string
    {
        $nonce = (string)$nonce;
        $time = (string)$time;
        $arr = [$nonce, $time, $secret];
        sort($arr);
        //返回新字符串
        $str = implode('', $arr);
        return sha1($str);
    }


    /**
     * 获取随机字符串
     * @return int
     */
    public function getNonce(): int
    {
        return rand(1000000000, 9999999999);
    }
}