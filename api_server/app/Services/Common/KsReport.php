<?php

declare(strict_types=1);
/**
 * 快手投放数据上报拉取
 * @see https://developers.e.kuaishou.com/docs?docType=DSP&documentId=1957
 * Author: wangshenyuan
 * Date: 2022/2/24
 */

namespace App\Services\Common;

use App\Exceptions\Handler;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KsReport
{
    const CREATIVE_REPORT = 'https://ad.e.kuaishou.com/rest/openapi/v1/report/creative_report';//自定义创意拉取地址
    const PROGRAM_CREATIVE_REPORT = 'https://ad.e.kuaishou.com/rest/openapi/v1/report/program_creative_report';//程序化创意拉取地址

    const TIMEOUT = 30;//接口请求超时时间（秒）

    private static $instance = null;

    private $appcode;

    private $advertisers = [];//广告主列表


    /**
     * @return KsReport|null
     */
    public static function getInstance(): ?KsReport
    {
        if (self::$instance == null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * 设置appcode
     * @param string $appcode
     * @return KsReport
     */
    public function setAppCode(string $appcode): KsReport
    {
        $this->appcode = $appcode;
        return $this;
    }

    /**
     * 获取广告主
     * @return array
     */
    public function getAdvertiser(): array
    {
        if (!isset($this->advertisers[$this->appcode])) {
            if (env('APP_ENV') === 'production') {
                $advertisers = DB::table('advertisers as a')
                    ->leftJoin('account as b', 'a.account_id', '=', 'b.id')
                    ->leftJoin('app_launch_platform as c', 'c.id', '=', 'b.platform_id')
                    ->where([['c.platform_sign', 'ks'], ['a.app_code', $this->appcode]])
                    ->select(['a.advertiser_id', 'a.advertiser_name', 'c.access_token'])
                    ->get()->toArray();
            } else {
                $advertisers = [
                    ['advertiser_id' => '11918987', 'advertiser_name' => '快手用户1640066991909', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '11922271', 'advertiser_name' => '快手用户1640066991909', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '11922272', 'advertiser_name' => '快手用户1640066991909', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '11922273', 'advertiser_name' => 'WiFi共享大师_执象_DT_站内&商店_3', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '11922274', 'advertiser_name' => 'WiFi共享大师_执象_DT_站内&商店_2', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '11922275', 'advertiser_name' => 'WiFi共享大师_执象_DT_站内&商店_1', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '11982838', 'advertiser_name' => 'WiFi共享大师_傲屹_DT_站内&商店_01-暂停', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '11985692', 'advertiser_name' => 'WiFi共享大师_傲屹_DT_站内&商店_02', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '11985693', 'advertiser_name' => 'WiFi共享大师_傲屹_DT_站内&商店_03-暂停', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '12454647', 'advertiser_name' => 'WiFi共享大师_傲屹_DT_站内&商店_04', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '12454648', 'advertiser_name' => 'WiFi共享大师_傲屹_DT_站内&商店_05', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '12454649', 'advertiser_name' => 'WiFi共享大师6', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '12454650', 'advertiser_name' => 'WiFi共享大师7', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '12454651', 'advertiser_name' => 'WiFi共享大师8', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '12618408', 'advertiser_name' => 'WiFi共享大师_盛拓_DT_站内&商店直投_01_双出价_陈', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '12635467', 'advertiser_name' => 'WiFi共享大师_盛拓_DT_站内&商店直投_02_双出价_陈', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '12635468', 'advertiser_name' => 'WiFi共享大师_盛拓_DT_站内&商店直投_03_双出价_陈', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                    ['advertiser_id' => '12635469', 'advertiser_name' => 'WiFi共享大师_盛拓_DT_站内&商店直投_04_关键行为_陈', 'access_token' => '3ecc428bc0593b076ac1afb1ffd961a7'],
                ];
            }
            $this->advertisers[$this->appcode] = $advertisers ?? [];
        }

        return $this->advertisers[$this->appcode];
    }

    /**
     * 拉取创意数据
     * @param string $temporal_granularity 时间粒度 天粒度（DAILY）／小时粒度（HOURLY）
     * @param string $start_date_min 增量拉取过滤筛选条件，格式【yyyy-MM-dd HH:mm】
     * @param string $end_date_min 增量拉取过滤筛选条件，格式【yyyy-MM-dd HH:mm】
     * @return array
     */
    public function CreativeReport(string $temporal_granularity, string $start_date_min, string $end_date_min): array
    {
        $api_rows = [
            self::CREATIVE_REPORT,//自定义创意数据
            self::PROGRAM_CREATIVE_REPORT,//程序化创意数据
        ];

        $advertiserIds = $this->getAdvertiser();
        $response_details = [];//拉取的结果集

        foreach ($api_rows as $api_url) {
            foreach ($advertiserIds as $row) {
                $advertiser_id = $row['advertiser_id'];
                $advertiser_name = $row['advertiser_name'];
                $access_token = $row['access_token'];

                $page = 1;
                $page_size = 2000;//接口最大支持查询2000
                $total_page = 1;//总页数，默认一页

                $send_data = [];
                $send_data['advertiser_id'] = $advertiser_id;
                $send_data['start_date_min'] = $start_date_min;
                $send_data['end_date_min'] = $end_date_min;
                $send_data['temporal_granularity'] = $temporal_granularity;
                $send_data['page_size'] = $page_size;

                while ($page <= $total_page) {
                    try {
                        $send_data['page'] = $page;
                        echo '快手请求参数：【' . $api_url . '】' . json_encode($send_data) . PHP_EOL;
//                        Log::info('快手请求参数：【' . $api_url . '】', $send_data);
                        $response = $this->sendData($api_url, $access_token, $send_data);

//                        Log::info('快手请求结果：', $response);
                        if (!isset($response['code']) || $response['code'] != 0) {
                            $res = var_export($response, true);
                            throw new \Exception('快手自定义创意拉取接口请求失败: ' . $res);
                        }
                        echo '快手请求结果：【' . $response['data']['total_count'] . '】' . PHP_EOL;
                        $total_page = ceil($response['data']['total_count'] / $page_size);//通过接口获取的总页数

                        $only_keys = [//需要获取的字段
                            'campaign_id',//广告计划 ID
                            'campaign_name',//广告计划名称
                            'unit_id',//广告组 ID
                            'unit_name',//广告组名称
                            'creative_id',//广告创意 ID
                            'creative_name',//广告创意名称
                            'stat_date',//数据日期，格式：YYYY-MM-DD
                            'stat_hour',//数据小时
                            'charge',//花费（元）
                            'aclick',//素材曝光数（展示数）
                            'bclick',//行为数（点击数）
                            'activation',//应用下载数据-激活数（激活数）
                            'key_action',//关键行为数（转化数）
                        ];

                        foreach ($response['data']['details'] as $detail) {
                            $detail = collect($detail)->only($only_keys)->toArray();
                            $detail['advertiser_id'] = $advertiser_id;
                            $detail['advertiser_name'] = $advertiser_name;
                            $response_details[] = $detail;
                        }

                    } catch (\Exception $e) {
                        Log::info('快手请求结果【失败】：' . $e->getMessage());
                        (new Handler())->pushMessage($e);
                    }

                    $page++;
                }

            }
        }
        echo '快手请求结果：【' . count($response_details) . '】' . PHP_EOL;
        return $response_details;
    }

    public function sendData($url, $access_token, $data)
    {
        $sendOpt = [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Content-Type' => 'application/json',
                'Access-Token' => $access_token,
            ],
            'json' => $data
        ];
        $curl = new Client();
        $response = $curl->post($url, $sendOpt)->getBody()->getContents();

        return json_decode($response, true);
    }

}
