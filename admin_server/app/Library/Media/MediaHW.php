<?php

declare(strict_types=1);

namespace App\Library\Media;

use App\Constants\MediaConst;
use App\Constants\RedisConst;
use App\Utils\Http;
use App\Utils\Log;
use App\Utils\RedisManager;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MediaHW extends MediaAbstract
{
    protected $mediaType = 'hw';

    protected $baseUri = 'https://ads.cloud.huawei.com';

    /**
     * @param string $uri
     * @param string $method
     * @param array $params
     * @param array $headers
     * @return array
     * @throws Exception
     */
    protected function request(string $uri = '', string $method = 'GET', array $params = [], array $headers = [])
    {
        $field = strtoupper($method) == 'POST' ? 'json' : 'query';

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken($this->getUserId(), $this->getSecret())
            ],
            $field => $params
        ];

        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MEDIA-HW】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getReasonPhrase());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);
        Log::http('info', '【MEDIA-HW】', ['uri' => $uri, 'options' => $options, 'response' => $content]);

        return (array)$content;
    }

    /**
     * @param string $date
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws Exception
     */
    public function income(string $date, int $page = 1, int $pageSize = 1000)
    {
        $params = [
            'start_date' => date('Y-m-d', strtotime($date)),
            'end_date' => date('Y-m-d', strtotime($date)),
            'filtering' => [
                'currency' => 'CNY'
            ],
            'group_by' => [
                'STAT_BREAK_DOWNS_PLACEMENT_ID',//按广告位进行分组
                'STAT_BREAK_DOWNS_APP_ID',//按App进行分组
                'STAT_BREAK_DOWNS_AD_TYPE',//按广告形式进行分组
            ],
            'time_granularity' => 'STAT_TIME_GRANULARITY_DAILY',
            'page' => $page,
            'page_size' => $pageSize,// 默认值：20，最大值 1000
        ];

        try {
            $content = $this->request('/openapi/monetization/reports/v1/publisher', 'POST', $params);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        if (($content['code'] ?? -1) !== '0') {
            throw new Exception($content['message'] ?? '未知错误');
        }

        $data = $content['data']['list'];

        $list = [];
        foreach ($data as $v) {
            if ($v['earnings'] <= 0) {
//                continue;
            }
            $list[] = [
                'date' => $v['stat_datetime'],
                'media_type' => $this->mediaType,
                'app_id' => $v['app_id'] ?? '',
                'app_name' => $v['app_name'] ?? '',
                'placement_id' => $v['placement_id'] ?? '',
                'placement_type' => MediaConst::PLACEMENT_TYPE_HW[$v['ad_type'] ?? ''] ?? ($v['ad_type'] ?? ''),
                'placement_name' => $v['placement_name'] ?? '',
                'revenue' => $v['earnings'] ?? 0,
                'ecpm' => ($v['show_count'] ?? 0) > 0 ? ($v['earnings'] ?? 0) * 1000 / $v['show_count'] : 0,
                'request' => $v['reached_ad_requests'] ?? 0,
                'fill' => $v['matched_reached_ad_requests'] ?? 0,
                'show' => $v['show_count'] ?? 0,
                'click' => $v['click_count'] ?? 0,
            ];
        }

        return [
            'list' => $list,
            'page_info' => [
                'page' => $content['data']['page_info']['page'] ?? 0,
                'page_size' => $content['data']['page_info']['page_size'] ?? 0,
                'total_number' => $content['data']['page_info']['total_number'] ?? 0,
                'total_page' => $content['data']['page_info']['total_page'] ?? 0
            ]
        ];
    }

    public function adList(){

    }

    /**
     * 获取Token
     * @param $userId
     * @param $secret
     * @return string
     * @throws Exception
     */
    protected function getToken($userId, $secret)
    {
        $redis = RedisManager::get();
        $redisKey = RedisConst::HUAWEI_MEDIA_TOKEN . $userId;
        if ($redis->exists($redisKey)) {
            return $redis->get($redisKey);
        }

        $baseUri = 'https://oauth-login.cloud.huawei.com';
        $uri = '/oauth2/v3/token';
        $options = [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $userId,
                'client_secret' => $secret,
            ]
        ];
        try {
            $response = make(Http::class)->get([
                'base_uri' => $baseUri
            ])->request('POST', $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MEDIA-HW】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getReasonPhrase());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);
        Log::http('info', '【MEDIA-HW】', ['uri' => $uri, 'options' => $options, 'response' => $content]);

        if (!$content['access_token'] ?? '') {
            throw new Exception('华为变现获取Token失败');
        }

        $redis->set($redisKey, $content['access_token']);
        $redis->expire($redisKey, $content['expires_in'] ?? 3600);

        return $content['access_token'];

    }
}