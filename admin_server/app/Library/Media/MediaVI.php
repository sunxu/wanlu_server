<?php

declare(strict_types=1);

namespace App\Library\Media;

use App\Utils\Log;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MediaVI extends MediaAbstract
{
    protected $mediaType = 'vi';

    protected $baseUri = 'https://adnet.vivo.com.cn';

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
                'token' => $this->getToken($this->getUserId(), $this->getSecret())
            ],
            $field => $params
        ];

        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MEDIA-VI】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getReasonPhrase());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);
        Log::http('info', '【MEDIA-VI】', ['uri' => $uri, 'options' => $options, 'response' => $content]);

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
            'startDate' => date('Ymd', strtotime($date)),
            'endDate' => date('Ymd', strtotime($date)),
            'accountName' => $this->getUserId(),
            'dimension' => 'position',//date/media/position,默认 position
            'page' => $page,
            'page_size' => $pageSize,// 默认值：20，最大值 1000
        ];

        try {
            $content = $this->request('/api/open/report', 'GET', $params);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        if (($content['code'] ?? -1) !== 0) {
            throw new Exception($content['message'] ?? '未知错误');
        }

        $data = $content['data'];

        $list = [];
        foreach ($data as $v) {
            if ($v['income'] <= 0) {
//                continue;
            }
            $list[] = [
                'date' => date('Y-m-d', strtotime($v['date'])),
                'media_type' => $this->mediaType,
                'app_id' => $v['mediaId'] ?? '',
                'app_name' => $v['mediaName'] ?? '',
                'placement_id' => $v['positionId'] ?? '',
                'placement_type' => '',// TODO
                'placement_name' => $v['positionName'] ?? '',
                'revenue' => $v['income'] ?? 0,
                'ecpm' => $v['ecpm'] ?? 0,
                'request' => 0,
                'fill' => 0,
                'show' => $v['view'] ?? 0,
                'click' => $v['click'] ?? 0,
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
        $time = time() * 1000;

        return base64_encode($time . ',' . hash('sha256', $userId . $secret . $time));
    }
}