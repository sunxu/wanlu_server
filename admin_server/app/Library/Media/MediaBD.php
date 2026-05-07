<?php

declare(strict_types=1);

namespace App\Library\Media;

use App\Constants\MediaConst;
use App\Utils\Log;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class MediaBD extends MediaAbstract
{
    protected $mediaType = 'bd';

    protected $baseUri = 'https://ubapi.baidu.com';

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
            'headers' => $this->getHeaders($method, $uri, $params),
            $field => $params
        ];

        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MEDIA-BD】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getReasonPhrase());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);
        Log::http('info', '【MEDIA-BD】', ['uri' => $uri, 'options' => $options, 'response' => $content]);

        if (($content['code'] ?? -1) !== 0) {
            Log::http('info', '【MEDIA-BD】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
            throw new Exception($content['message'] ?? '未知错误');
        }

        return $content['data'] ?? [];
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
        $d = date('Ymd', strtotime($date));
        $uri = '/ssp/1/sspservice/app/app-report/get-app-report';

        $params = [
            'beginDate' => $d,
            'endDate' => $d,
        ];

        try {
            $data = $this->request($uri, 'GET', $params);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $list = [];

        foreach ($data as $v) {
            if (empty($v['adPositionId'])) {
                continue;
            }
            $list[] = [
                'date' => date('Y-m-d', strtotime($v['time'])),
                'media_type' => $this->mediaType,
                'app_id' => $v['appId'] ?? '',
                'app_name' => $v['appName'] ?? '',
                'placement_id' => $v['adPositionId'] ?? '',
                'placement_type' => MediaConst::PLACEMENT_TYPE_BD[$v['adTypeName'] ?? ''] ?? '',
                'placement_name' => $v['adPositionName'] ?? '',
                'revenue' => floatval($v['income'] ?? 0),
                'ecpm' => floatval($v['ecpm'] ?? 0),
                'request' => intval($v['request'] ?? 0),
                'fill' => intval($v['effectRequest'] ?? 0),
                'show' => intval($v['view'] ?? 0),
                'click' => intval($v['click'] ?? 0),
            ];
        }

        return [
            'list' => $list,
            'page_info' => [
                'page' => $content['page_info']['current_page'] ?? $page,
                'page_size' => $content['page_info']['page_size'] ?? $pageSize,
                'total_number' => $content['page_info']['total_count'] ?? 0,
                'total_page' => ceil(($content['page_info']['total_count'] ?? 0) / ($content['page_info']['page_size'] ?? $pageSize))
            ]
        ];
    }

    /**
     * 代码位列表
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws Exception
     */
    public function adList(int $page = 1, int $pageSize = 1000)
    {
        $uri = '/ssp/1/sspservice/appadpos/app/adpos/page-query';

        $params = [
            'page_no' => $page,
            'page_size' => $pageSize,
        ];

        try {
            $data = $this->request($uri, 'POST', $params);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $adTypes = [
            33 => 'splash',
            34 => 'inter',
            36 => 'native',
            52 => 'full_video',
            44 => 'reword_video',
        ];

        $list = [];

        foreach ($data['data'] ?? [] as $v) {
            $list[] = [
                'media_type' => $this->mediaType,
                'app_id' => $v['app_sid'] ?? '',
                'app_name' => $v['app_name'] ?? '',
                'placement_id' => $v['tu_id'] ?? '',
                'placement_type' => $adTypes[$v['ad_type']] ?? '',
                'placement_name' => $v['ad_name'] ?? '',
                'status' => ($v['ad_status'] ?? 0) == 1 ? 1 : 2, // 1-启用 2-暂停
                'ecpm' => floatval($v['cpm'] ?? 0),
                'bidding' => ($v['price_type'] ?? 1) == 2 ? 1 : 0,//1 底价，2 bidding
            ];
        }

        return [
            'list' => $list,
            'page_info' => [
                'page' => $data['page_no'] ?? $page,
                'page_size' => $data['page_size'] ?? $pageSize,
                'total_number' => $data['total_count'] ?? 0,
                'total_page' => ceil(($data['total_count'] ?? 0) / ($data['page_size'] ?? $pageSize))
            ]
        ];
    }

    /**
     * 签名
     * @param string $method
     * @param string $uri
     * @param int $timestamp
     * @param string $contentType
     * @param string $body
     * @return string
     */
    protected function sign(string $method, string $uri, int $timestamp, string $contentType, string $body): string
    {
        $method = strtoupper($method);
        $bodyMd5 = $method == 'POST' ? md5($body) : '';

        $message = $this->userId . "\n" . $method . "\n" . $uri . "\n" . $timestamp . "\n" . $contentType . "\n" . $bodyMd5;

        $privateKey = $this->formatKey($this->secret, 'private');
        $binarySignature = '';
        @openssl_sign($message, $binarySignature, openssl_get_privatekey($privateKey), OPENSSL_ALGO_SHA1);
        return base64_encode($binarySignature);
    }

    protected function formatKey(string $key, string $type = 'private')
    {
        // 秘钥标识
        $code = $type === 'public' ? 'PUBLIC' : 'DSA PRIVATE';
        // 格式化
        $format = sprintf("-----BEGIN %s KEY-----", $code);
        $format .= "\n" . chunk_split($key, 64, "\n");
        $format .= sprintf("-----END %s KEY-----", $code);
        // 返回
        return $format;
    }

    protected function getHeaders(string $method, string $uri, array $params)
    {
        $timestamp = time();

        if (strtoupper($method) == 'GET') {
            $uri = $uri . '?' . urldecode(http_build_query($params));
            $contentType = '';
            $body = '';

        } else {
            $contentType = 'application/json';
            $body = json_encode($params);
        }

        return [
            'x-ub-date' => $timestamp,
            'x-ub-authorization' => $this->userId . ':' . $this->sign($method, $uri, $timestamp, $contentType, $body),
        ];
    }

    /**
     * @return Client
     */
    protected function getClient()
    {
        return make(Client::class, [[
            'base_uri' => $this->baseUri
        ]]);
    }
}
