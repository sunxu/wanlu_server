<?php

declare(strict_types=1);

namespace App\Library\Media;

use App\Constants\MediaConst;
use App\Constants\RedisConst;
use App\Library\AppStore\StoreException;
use App\Utils\Http;
use App\Utils\Log;
use App\Utils\RedisManager;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Redis\Redis;

class MediaOP extends MediaAbstract
{
    protected $mediaType = 'op';

    protected $baseUri = 'https://uapi.ads.heytapmobi.com/';

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
            $field => array_merge($params, [
                'userId' => $this->getUserId(),
                'token' => $this->getToken($this->getUserId(), $this->getSecret(), time())
            ])
        ];

        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MEDIA-OP】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getReasonPhrase());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);
        Log::http('info', '【MEDIA-OP】', ['uri' => $uri, 'options' => $options, 'response' => $content]);

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
            'startTime' => date('Ymd', strtotime($date)),
            'endTime' => date('Ymd', strtotime($date)),
        ];

        try {
            $content = $this->request('/union/api/report/posQuery', 'GET', $params);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        if (($content['code'] ?? -1) !== 0) {
            throw new Exception($content['message'] ?? '未知错误');
        }

        $data = $content['data'] ?? [];

        $list = [];
        foreach ($data as $v) {
            if ($v['income'] <= 0) {
//                continue;
            }
            $list[] = [
                'date' => date('Y-m-d', strtotime($v['time'])),
                'media_type' => $this->mediaType,
                'app_id' => $v['appId'] ?? '',
                'app_name' => $v['appName'] ?? '',
                'placement_id' => $v['posId'] ?? '',
                'placement_type' => MediaConst::PLACEMENT_TYPE_OP[$v['showTypeId'] ?? ''] ?? '',
                'placement_name' => $v['posName'] ?? '',
                'revenue' => $v['income'] ?? 0,
                'ecpm' => $v['ecpm'] ?? 0,
                'request' => $v['bfReqNums'] ?? 0,
                'fill' => $v['bfSuccReqNums'] ?? 0,
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
     * @param $timeStamp
     * @return string
     */
    protected function getToken($userId, $secret, $timeStamp)
    {
        $sign = sha1($userId . $secret . $timeStamp);
        return base64_encode($userId . ',' . time() . ',' . $sign);
    }
}