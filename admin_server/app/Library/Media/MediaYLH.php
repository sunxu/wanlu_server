<?php

declare(strict_types=1);

namespace App\Library\Media;

use App\Constants\MediaConst;
use App\Utils\Log;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MediaYLH extends MediaAbstract
{
    protected $mediaType = 'ylh';

    protected $baseUri = 'https://api.adnet.qq.com';

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
        $headers['token'] = $this->getToken($this->getUserId(), $this->getSecret());
        $params['member_id'] = $this->getUserId();

        $field = strtoupper($method) == 'POST' ? 'form_params' : 'query';

        $options = [
            'headers' => $headers,
            $field => $params
        ];

        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MEDIA-YLH】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getReasonPhrase());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);
        Log::http('info', '【MEDIA-YLH】', ['uri' => $uri, 'options' => $options, 'response' => $content]);

        if (($content['code'] ?? -1) !== 0) {
            throw new Exception($content['message'] ?? '未知错误');
        }

        return $content['data'];
    }

    /**
     * @param string $date
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws Exception
     */
    public function income(string $date, int $page = 1, int $pageSize = 100)
    {
        $params = [
            'start_date' => date('Ymd', strtotime($date)),
            'end_date' => date('Ymd', strtotime($date)),
            'page' => $page,
            'page_size' => $pageSize
        ];

        try {
            $data = $this->request('open/v1.1/report/get', 'GET', $params);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $list = [];
        foreach ($data['list'] ?? [] as $v) {
            if (($v['is_summary'] ?? false)) {
                continue;
            }
            $list[] = [
                'date' => $v['date'] ?? '',
                'media_type' => $this->mediaType,
                'app_id' => $v['app_id'] ?? '',
                'app_name' => $v['medium_name'] ?? '',
                'placement_id' => $v['placement_id'] ?? '',
                'placement_type' => MediaConst::PLACEMENT_TYPE_YLH[$v['custom_position_scene_level2'] ?? 0] ?? '',
                'placement_name' => $v['placement_name'] ?? '',
                'revenue' => $v['revenue'] ?? 0,
                'ecpm' => $v['ecpm'] ?? 0,
                'request' => $v['request_count'] ?? 0,
                'fill' => $v['return_count'] ?? 0,
                'show' => $v['pv'] ?? 0,
                'click' => $v['click'] ?? 0,
            ];
        }

        return [
            'list' => $list,
            'page_info' => [
                'page' => $data['page_info']['page'] ?? $page,
                'page_size' => $data['page_info']['page_size'] ?? $pageSize,
                'total_number' => $data['page_info']['total_number'] ?? 0,
                'total_page' => $data['page_info']['total_page'] ?? $page
            ]
        ];
    }

    /**
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws Exception
     */
    public function adList(int $page = 1, int $pageSize = 100)
    {
        $params = [
            'page' => $page,
            'page_size' => $pageSize
        ];

        try {
            $data = $this->request('/open/v1.1/placement/list', 'POST', $params);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $list = [];

        foreach ($data['list'] ?? [] as $v) {
            $list[] = [
                'media_type' => $this->mediaType,
                'app_id' => $v['app_id'] ?? '',
                'app_name' => $v['medium_name'] ?? '',
                'placement_id' => $v['placement_id'] ?? '',
                'placement_type' => MediaConst::PLACEMENT_TYPE_YLH[$v['custom_position_scene_level2'] ?? 0] ?? '',
                'placement_name' => $v['placement_name'] ?? '',
                'status' => $v['status'] == 'Normal' ? 1 : 2,
                'ecpm' => floatval($v['ecpm_price'] ?? 0) / 100,
                'bidding' => stristr($v['price_strategy_type'], 'bidding') ? 1 : 0
            ];
        }

        return [
            'list' => $list,
            'page_info' => [
                'page' => $data['page_info']['page'] ?? $page,
                'page_size' => $data['page_info']['page_size'] ?? $pageSize,
                'total_number' => $data['page_info'][' total_number'] ?? 0,
                'total_page' => $data['page_info']['total_page'] ?? $page,
            ]
        ];
    }

    /**
     * 获取Token
     * @param $userId
     * @param $secret
     * @return string
     */
    protected function getToken($userId, $secret)
    {
        $time = time();
        return base64_encode($userId . ',' . $time . ',' . sha1($userId . $secret . $time));
    }
}