<?php

declare(strict_types=1);

namespace App\Library\Media;

use App\Constants\MediaConst;
use App\Utils\Log;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MediaCSJ extends MediaAbstract
{
    protected $mediaType = 'csj';

    protected $baseUri = 'https://open-api.csjplatform.com';

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
            Log::http('error', '【MEDIA-CSJ】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getReasonPhrase());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        if (($content['Code'] ?? -1) !== '100') {
            Log::http('info', '【MEDIA-CSJ】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
            throw new Exception($content['Message'] ?? '未知错误');
        }

        return $content['Data'] ?? [];
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
            'user_id' => $this->getUserId(),
            'role_id' => $this->getUserId(),
            'timestamp' => time(),
            'version' => '2.0',
            'date' => date('Y-m-d', strtotime($date)),
            'region' => 'cn',
            'sign_type' => 'MD5'
        ];
        $params['sign'] = $this->signIncome($params, $this->getSecret());

        try {
            $data = $this->request('union_media/open_api/rt/income', 'GET', $params);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $list = [];
        foreach ($data[$params['date']] ?? [] as $v) {
            if (empty($v['ad_slot_id'])) {
                continue;
            }
            $list[] = [
                'date' => $v['date'] ?? '',
                'media_type' => $this->mediaType,
                'app_id' => $v['app_id'] ?? '',
                'app_name' => $v['app_name'] ?? '',
                'placement_id' => $v['ad_slot_id'] ?? '',
                'placement_type' => MediaConst::PLACEMENT_TYPE_CSJ[$v['ad_slot_type'] ?? 0] ?? '',
                'placement_name' => $v['code_name'] ?? '',
                'revenue' => $v['revenue'] ?? 0,
                'ecpm' => $v['ecpm'] ?? 0,
                'request' => $v['request'] ?? 0,
                'fill' => $v['return'] ?? 0,
                'show' => $v['show'] ?? 0,
                'click' => $v['click'] ?? 0,
            ];
        }

        return [
            'list' => $list,
            'page_info' => [
                'page' => $page,
                'page_size' => count($list),
                'total_number' => count($list),
                'total_page' => $page
            ]
        ];
    }

    public function adList(int $page = 1, int $pageSize = 100)
    {
        $timestamp = time();
        $nonce = $this->getNonce();
        $params = [
            'user_id' => $this->getUserId(),
            'role_id' => $this->getUserId(),
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'version' => '1.0',
            'sign' => $this->signAd($timestamp, $nonce, $this->getSecret()),
            'page' => $page,
            'page_size' => $pageSize
        ];

        try {
            $data = $this->requestAd('/union/media/open_api/code/query', 'POST', $params);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        // 2-正式 3-停止 10-启用
        $status = [
            2 => 1,
            3 => 2,
            6 => 2,
            10 => 1
        ];
        $list = [];

        foreach ($data['ad_slot_list'] ?? [] as $v) {
            $list[] = [
                'media_type' => $this->mediaType,
                'app_id' => $v['app_id'] ?? '',
                'app_name' => $v['app_name'] ?? '',
                'placement_id' => $v['ad_slot_id'] ?? '',
                'placement_type' => MediaConst::PLACEMENT_TYPE_CSJ[$v['ad_slot_type'] ?? 0] ?? '',
                'placement_name' => $v['ad_slot_name'] ?? '',
                'status' => $status[$v['status']] ?? 0,
                'ecpm' => floatval($v['cpm'] ?? 0),
                'bidding' => ($v['bidding_type'] ?? 0) > 0 ? 1 : 0
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
     * 签名
     * @param array $params
     * @param string $secret
     * @return string
     */
    protected function signIncome(array $params, string $secret): string
    {
        $params = array_filter($params, 'strlen');

        ksort($params);

        return md5(urldecode(http_build_query($params)) . $secret);
    }

    protected function requestAd(string $uri = '', string $method = 'GET', array $params = [], array $headers = [])
    {
        $field = strtoupper($method) == 'POST' ? 'json' : 'query';

        $options = [
            'headers' => $headers,
            $field => $params
        ];

        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MEDIA-CJS】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getReasonPhrase());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        if (($content['code'] ?? -1) !== 0) {
            Log::http('info', '【MEDIA-CSJ】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
            throw new Exception(isset($content['message']) ? (is_array($content['message']) ? json_encode($content['message']) : '') : '未知错误');
        }

        return $content['data'] ?? [];
    }

    /**
     * @param int $timestamp
     * @param int $nonce
     * @param string $secret
     * @return string
     */
    protected function signAd(int $timestamp, int $nonce, string $secret)
    {
        $arr = [(string)$nonce, (string)$timestamp, $secret];
        sort($arr);
        $str = implode('', $arr);
        return sha1($str);
    }

    /**
     * @return int
     */
    protected function getNonce()
    {
        return rand(1000000000, 9999999999);
    }
}