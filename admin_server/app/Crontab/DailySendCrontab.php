<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/7/15
 */

namespace App\Crontab;

use App\Constants\AppConst;
use App\Library\Aliyun\Oss;
use App\Model\App;
use App\Utils\Ding;

class DailySendCrontab
{
    public function execute()
    {
        $app_map = App::query()
            ->where('ad_status', 1)//自投产品
            ->where('type', 1000)//工具产品
            ->pluck('app_name', 'app_code')
            ->toArray();
        foreach ($app_map as $app_code => $app_name) {
            $image_list = [];
            $image_list[] = $this->AvgShow($app_code, AppConst::LAUNCH_TYPE_CLSC);
            $image_list[] = $this->AvgShow($app_code, AppConst::LAUNCH_TYPE_GJXW);
            $image_list[] = $this->Keep($app_code, AppConst::LAUNCH_TYPE_CLSC);
            $image_list[] = $this->Keep($app_code, AppConst::LAUNCH_TYPE_GJXW);
            $image_list[] = $this->Duration($app_code);

            $data = [];
            $data['date'] = date('Y-m-d');
            $data['app_name'] = $app_name;
            $data['image_list'] = $image_list;

            $this->send($data);

        }
    }

    public function Keep($app_code, $launch_type)
    {
        $domain = 'http://127.0.0.1:9521';
        $link_keep = '/uc/ding-daily/keep?';
        $params = [];
        $params['app_code'] = $app_code;
        $params['launch_type'] = $launch_type;
        $params['secret'] = AppConst::DAILY_SECRET;
        $url = $domain . $link_keep . http_build_query($params);
        $image_title = '次留率-' . AppConst::LAUNCH_TYPE_NAME_MAP[$launch_type];
        $image_link = $this->create($url);
        return [
            'image_link' => $image_link,
            'image_title' => $image_title
        ];
    }


    public function AvgShow($app_code, $launch_type)
    {
        $domain = 'http://127.0.0.1:9521';
        $link_keep = '/uc/ding-daily/avg-show?';
        $params = [];
        $params['app_code'] = $app_code;
        $params['launch_type'] = $launch_type;
        $params['secret'] = AppConst::DAILY_SECRET;
        $url = $domain . $link_keep . http_build_query($params);
        $image_title = '人均展示-' . AppConst::LAUNCH_TYPE_NAME_MAP[$launch_type];
        $image_link = $this->create($url);
        return [
            'image_link' => $image_link,
            'image_title' => $image_title
        ];
    }


    public function Duration($app_code)
    {
        $domain = 'http://127.0.0.1:9521';
        $link_keep = '/uc/ding-daily/duration?';
        $params = [];
        $params['app_code'] = $app_code;
        $params['secret'] = AppConst::DAILY_SECRET;
        $url = $domain . $link_keep . http_build_query($params);
        $image_title = '心跳时长';
        $image_link = $this->create($url);
        return [
            'image_link' => $image_link,
            'image_title' => $image_title
        ];
    }

    /**
     * 创建图片
     * @param $url
     * @return mixed
     */
    public function create($url)
    {
        /*执行系统命令生成图片*/
        $img_path = BASE_PATH . '/runtime/logs/' . uniqid() . '.jpg';
        $cli_command = 'wkhtmltoimage --width 1400 --quality 100 "' . $url . '" ' . $img_path;
        exec($cli_command);//需去掉ini exec方法禁用设置

        /*上传图片至oss*/
        $Oss = make(Oss::class);
        $res = $Oss->putPublicBucket($img_path);
        unlink($img_path);
        return $res['oss_request_url'];

    }

    public function send($data)
    {

        /*发送图片到钉钉*/
        $message = '**日期：' . $data['date'] . '**';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '**产品：' . $data['app_name'] . '**';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= '---';
        foreach ($data['image_list'] as $row) {
            $message .= PHP_EOL;
            $message .= '**' . $row['image_title'] . '**';
            $message .= '![](' . $row['image_link'] . ')';
        }

        make(Ding::class)->setRobotCode('daily_report')->pushMarkdown('日报', $message);

    }
}