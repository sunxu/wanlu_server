<?php

declare(strict_types=1);

namespace App\Library\Media;

use App\Constants\MediaConst;
use App\Utils\Log;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MediaKS extends MediaAbstract
{
    protected $mediaType = 'ks';

    protected $baseUri = 'https://ssp.e.kuaishou.com';

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
            'headers' => $headers,
            $field => $params
        ];

        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MEDIA-KS】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getReasonPhrase());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        if (($content['result'] ?? -1) !== 1) {
            Log::http('info', '【MEDIA-KS】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
            throw new Exception($content['error_msg'] ?? '未知错误');
        }

        return $content;
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
            'ak' => $this->getUserId(),
            'timestamp' => time(),
            'date' => date('Y-m-d', strtotime($date)),
            'page' => $page,
        ];

        $uri = '/api/report/dailyShare';
        $params['sign'] = $this->sign($params, $uri);

        try {
            $data = $this->request($this->getUri($uri, $params), 'GET', $params);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $list = [];

        foreach ($data['data'] as $v) {
            $list[] = [
                'date' => $v['date'] ?? '',
                'media_type' => $this->mediaType,
                'app_id' => $v['app_id'] ?? '',
                'app_name' => $v['app_name'] ?? '',
                'placement_id' => $v['position_id'] ?? '',
                'placement_type' => MediaConst::PLACEMENT_TYPE_KS[$v['ad_style'] ?? 0] ?? '',
                'placement_name' => $v['position_name'] ?? '',
                'revenue' => $v['share'] ?? 0,
                'ecpm' => $v['ecpm'] ?? 0,
                'request' => $v['req_cnt'] ?? 0,
                'fill' => $v['resp_cnt'] ?? 0,
                'show' => $v['impression'] ?? 0,
                'click' => $v['click'] ?? 0,
            ];
        }

        return [
            'list' => $list,
            'page_info' => [
                'page' => $data['page_info']['current_page'] ?? 0,
                'page_size' => $data['page_info']['page_size'] ?? 0,
                'total_number' => $data['page_info']['total_count'] ?? 0,
                'total_page' => ceil(($data['page_info']['total_count'] ?? 0) / ($data['page_info']['page_size'] ?? $pageSize))
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
            'ak' => $this->getUserId(),
            'timestamp' => time(),
            'page' => $page,
            'pageSize' => $pageSize,
        ];

        $uri = 'api/position/get';
        $params['sign'] = $this->sign($params, $uri);

        try {
            $data = $this->request($this->getUri($uri, $params), 'POST', $params);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $list = [];

        foreach ($data['data'] as $v) {
            $list[] = [
                'media_type' => $this->mediaType,
                'app_id' => $v['app_id'] ?? '',
                'app_name' => $v['app_name'] ?? '',
                'placement_id' => $v['position_id'] ?? '',
                'placement_type' => MediaConst::PLACEMENT_TYPE_KS[$v['ad_style'] ?? 0] ?? '',
                'placement_name' => $v['name'] ?? '',
                'status' => 1,
                'ecpm' => floatval($v['cpm_floor'] ?? 0),
                'bidding' => ($v['price_strategy'] ?? 1) == 2 ? 1 : 0,//1 目标价，2 实时竞价
            ];
        }

        return [
            'list' => $list,
            'page_info' => [
                'page' => $data['pageInfo']['page'] ?? $page,
                'page_size' => $data['pageInfo']['pageSize'] ?? $pageSize,
                'total_number' => $data['pageInfo']['totalCount'] ?? 0,
                'total_page' => ceil(($data['pageInfo']['totalCount'] ?? 0) / ($data['pageInfo']['pageSize'] ?? $pageSize))
            ]
        ];
    }

    /**
     * 签名
     * @param array $params
     * @param string $uri
     * @return string
     * @throws Exception
     */
    protected function sign(array $params, string $uri): string
    {
        $params['sk'] = $this->getSecret();

        return md5($this->getUri($uri, $params));
    }

    protected function getUri(string $uri, array $params)
    {
        $params = array_filter($params, 'strlen');
        ksort($params);
        return sprintf('/%s?%s', ltrim($uri, '/'), urldecode(http_build_query($params)));
    }

}