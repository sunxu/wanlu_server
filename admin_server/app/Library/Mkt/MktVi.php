<?php

declare(strict_types=1);

namespace App\Library\Mkt;

use App\Utils\Log;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MktVi extends MktAbstract
{
    protected $baseUri = 'https://marketing-api.vivo.com.cn';

    /**
     * 请求
     * @param string $uri
     * @param string $method
     * @param array $params
     * @param array $headers
     * @return array
     * @throws Exception
     */
    public function request(string $uri = '', string $method = 'GET', array $params = [], array $headers = []): array
    {
        $field = strtoupper($method) == 'POST' ? 'json' : 'query';

        if ($field == 'query') {
            foreach ($params as &$v) {
                $v = is_array($v) ? json_encode($v) : $v;
            }
        }

        $options = [
            'headers' => $headers,
            $field => $params
        ];

        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MKT-VI】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        if (($content['code'] ?? -1) !== 0) {
            Log::http('info', '【MKT-VI】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
            throw new Exception($content['message'] ?? '响应异常');
        }

        return (array)$content['data'];
    }

    /**
     * @param string $api
     * @param string $method
     * @param array $params
     * @param array $headers
     * @return array
     * @throws Exception
     */
    public function requestByToken(string $api = '', string $method = 'GET', array $params = [], array $headers = [])
    {
        $getParams = [
            'access_token' => $this->getToken(),
            'timestamp' => time() * 1000,
            'nonce' => uniqid()
        ];
        if (!empty($params['advertiser_id'])) {
            $getParams['advertiser_id'] = $params['advertiser_id'];
        }
        $api = $api . '?' . urldecode(http_build_query($getParams));
        return $this->request($api, $method, $params, $headers);
    }

    /**
     * 获取Token
     * @param string $authCode
     * @return array
     * @throws Exception
     */
    public function authAccessToken(string $authCode)
    {
        $data = $this->request('/openapi/v1/oauth2/token', 'GET', [
            'client_id' => $this->getAppId(),
            'client_secret' => $this->getAppSecret(),
            'grant_type' => 'code',
            'code' => $authCode
        ]);

        if (empty($data['access_token'])) {
            throw new Exception(__CLASS__ . ':' . __FUNCTION__ . '响应异常');
        }

        return [
            'app_id' => $this->getAppId(),
            'access_token' => $data['access_token'],
            'access_time' => intval($data['token_date'] / 1000),
            'refresh_token' => $data['refresh_token'],
            'refresh_time' => intval($data['refresh_token_date'] / 1000),
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function authUserInfo()
    {
        $data = $this->requestByToken('/openapi/v1/account/fetch', 'POST', []);

        return [
            'user_id' => $data['uuid'],
            'user_email' => $data['email'] ?? '',
            'user_name' => $data['name'] ?? '',
            'user_level' => $data['level'] ?? -1
        ];
    }

    /**
     * 刷新Token
     * @param string $refreshToken
     * @return array
     * @throws Exception
     */
    public function authRefreshToken(string $refreshToken)
    {
        $data = $this->request('/openapi/v1/oauth2/refreshToken', 'GET', [
            'client_id' => $this->getAppId(),
            'client_secret' => $this->getAppSecret(),
            'refresh_token' => $refreshToken
        ]);

        if (empty($data['access_token'])) {
            throw new Exception(__CLASS__ . ':' . __FUNCTION__ . '响应异常');
        }

        return [
            'app_id' => $this->getAppId(),
            'access_token' => $data['access_token'],
            'access_time' => intval($data['token_date'] / 1000),
            'refresh_token' => $data['refresh_token'],
            'refresh_time' => intval($data['refresh_token_date'] / 1000),
        ];
    }

    /**
     * 查询二代下属广告主
     * @param string $accountId
     * @param int $page
     * @param int $pageSize
     * @param array $list
     * @return array
     * @throws Exception
     */
    public function advertiserGet(string $accountId, int $page = 1, int $pageSize = 100, $list = [])
    {
        $data = $this->requestByToken('/openapi/v1/account/advertiser/query', 'POST', [
            'pageIndex' => $page,
            'pageSize' => $pageSize,
        ]);

        foreach (($data['list'] ?? []) as $v) {
            $list[] = [
                'account_id' => $accountId,
                'advertiser_id' => $v['uuid'],
                'advertiser_name' => $v['name'],
            ];
        }

        if (($data['pageInfo']['pageCount'] ?? 0) > $page) {
            return $this->advertiserGet($accountId, $page++, $pageSize, $list);
        }

        return $list;
    }

    /**
     * 广告主数据
     * @param string $advertiserId
     * @param string $date
     * @param string $granularity
     * @return array
     * @throws Exception
     */
    public function reportAdvertiserGet(string $advertiserId, string $date, $granularity = 'daily')
    {
        $timeGranularity = strtoupper($granularity) == 'DAILY' ? 'DAY' : 'HOUR';

        $d = date('Ymd', strtotime($date));
        $data = $this->requestByToken('/openapi/v1/adstatement/summary/query', 'POST', [
            'advertiser_id' => $advertiserId,
            'startDate' => $d,
            'endDate' => $d,
            'summaryType' => $timeGranularity,// 时间粒度
            'level' => 'ACCOUNT',
            'pageSize' => 200,
        ]);

        $list = [];
        foreach ($data['items'] ?? [] as $v) {
            $d = $this->reportFieldsFormat($v);
            $d['date'] = $date;
            $d['advertiser_id'] = $advertiserId;
            if (strtoupper($granularity) == 'HOURLY') {
                $d['hour'] = date('H', strtotime($v['reportTime']));
            }
            $list[] = $d;
        }

        return $list;
    }

    /**
     * 计划列表
     * @param string $advertiserId
     * @param int $page
     * @param int $pageSize
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function planGet(string $advertiserId, int $page = 1, int $pageSize = 100, array $ids = [])
    {
        $pageSize = $pageSize < 1 ? 1 : ($pageSize > 100 ? 100 : $pageSize);
        $page = $page < 1 ? 1 : $page;

        $params = [
            'advertiser_id' => $advertiserId,
            'pageIndex' => $page,
            'pageSize' => $pageSize,
        ];

        if (!empty($ids)) {
            $params['ids'] = $ids;
        }

        $data = $this->requestByToken('/openapi/v1/ad/campaign/pageInfo', 'POST', $params);

        $putStatus = [
            0 => 1,
            1 => 2,
        ];
        $list = [];
        foreach ($data['list'] ?? [] as $v) {
            $list[] = [
                'plan_id' => $v['id'],
                'plan_name' => $v['name'],
                'advertiser_id' => $v['userId'],
                'put_status' => $putStatus[$v['pauseState']] ?? ($v['pauseState'] == 1 ? 3 : 0),
//                'budget_mode' => $v['budget_mode'],
                'budget_amount' => round($v['dailyBudget'] / 100000, 2),
                'create_time' => date('Y-m-d H:i:s', $v['createDate'] / 1000),
            ];
        }

        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($data['pageInfo'])
        ];
    }

    /**
     * 报表-计划
     * @param string $advertiserId
     * @param string $date
     * @param array $planIds
     * @param string $granularity
     * @param int $page
     * @param int $pageSize
     * @param string $lastId
     * @return array
     * @throws Exception
     */
    public function reportPlanGet(string $advertiserId, string $date, array $planIds = [], $granularity = 'daily', int $page = 1, int $pageSize = 100, string $lastId = '')
    {
        $timeGranularity = strtoupper($granularity) == 'DAILY' ? 'DAY' : 'HOUR';

        $d = date('Ymd', strtotime($date));
        $data = $this->requestByToken('/openapi/v1/adstatement/summary/query', 'POST', [
            'advertiser_id' => $advertiserId,
            'startDate' => $d,
            'endDate' => $d,
            'summaryType' => $timeGranularity,// 时间粒度
            'level' => 'CAMPAIGN',
            'lastId' => $lastId,
            'pageSize' => $pageSize,
        ]);

        $list = [];
        foreach ($data['items'] ?? [] as $v) {
            $d = $this->reportFieldsFormat($v);
            $d['date'] = $date;
            $d['advertiser_id'] = $advertiserId;
            $d['plan_id'] = $v['campaignId'];
            if (strtoupper($granularity) == 'HOURLY') {
                $d['hour'] = date('H', strtotime($v['reportTime']));
            }
            $list[] = $d;
        }
        return [
            'last_id' => $data['lastId'],
            'list' => $list,
            // 虚构分页数据
            'page_info' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total_number' => $page * $pageSize,
                'total_page' => count($list) == $pageSize ? $page++ : $page
            ]
        ];
    }

    /**
     * 更新计划
     * @param string $advertiserId
     * @param string $planId
     * @param array $params
     * @return bool
     * @throws Exception
     */
    public function planUpdate(string $advertiserId, string $planId, array $params = [])
    {
//        $data = $this->requestByToken('/open_api/2/ad/update/', 'POST', array_merge([
//            'advertiser_id' => $advertiserId,
//            'ad_id' => $planId,
//        ], $params));
//
//        return $planId == $data['ad_id'];
    }

    /**
     *计划更新状态
     * @param string $advertiserId
     * @param string $planId
     * @param string $putStatus
     * @return bool
     * @throws Exception
     */
    public function planUpdateStatus(string $advertiserId, string $planId, string $putStatus = 'enable')
    {
//        $data = $this->requestByToken('/open_api/2/ad/update/status/', 'POST', [
//            'advertiser_id' => $advertiserId,
//            'ad_ids' => [$planId],
//            'opt_status' => $putStatus,
//        ]);
//
//        return in_array($planId, $data['ad_ids']);
    }

    /**
     * 计划更新预算
     * @param string $advertiserId
     * @param string $planId
     * @param float $budgetAmount
     * @return bool
     * @throws Exception
     */
    public function planUpdateBudget(string $advertiserId, string $planId, float $budgetAmount)
    {
//        $data = $this->requestByToken('/open_api/2/ad/update/budget/', 'POST', [
//            'advertiser_id' => $advertiserId,
//            'data' => [
//                [
//                    'ad_id' => $planId,
//                    'budget' => $budgetAmount,
//                ]
//            ],
//        ]);
//
//        return in_array($planId, $data['ad_ids']);
    }

    /**
     * 报表数据字短
     * @param bool $isNew
     * @return array
     */
    private function reportFields($isNew = false)
    {
        return [
            'cost' => 'spent',// 展现数据-总花费
            'show' => 'showCount',// 展现数据-展示数
//            'avg_show_cost' => 'cpm_platform',// 展现数据-平均千次展现费用
            'click' => 'clickCount',// 展现数据-点击数
//            'avg_click_cost' => 'cpc_platform',// 展现数据-平均点击单价
//            'click_rate' => 'ctr',// 展现数据-点击率
//            'convert' => 'convert_cnt',// 转化数据-转化数
//            'convert_cost' => 'conversion_cost',// 转化数据-转化成本
//            'convert_rate' => 'conversion_rate',// 转化数据-转化率
            //'deep_convert', // 转化数据-深度转化数
            //'deep_convert_cost', // 转化数据-深度转化成本
            //'deep_convert_rate', // 转化数据-深度转化率
            //'install_finish',// 应用下载广告数据-安卓安装完成数
            'active' => 'backActivateCount',// 应用下载广告数据-激活数
//            'active_cost' => 'active_cost', // 应用下载广告数据-激活成本
//            'active_rate' => 'active_rate', // 应用下载广告数据-激活率
            'next_day_open' => 'customRetainCount',// 应用下载广告数据-次留数
//            'next_day_open_cost' => 'attribution_next_day_open_cost',// 应用下载广告数据-次留成本
//            'next_day_open_rate' => 'attribution_next_day_open_rate',// 应用下载广告数据-次留率
            'key_action' => 'personalizedEvents',// 应用下载广告数据-关键行为数
//            'key_action_cost' => 'game_addiction_cost', // 应用下载广告数据-关键行为成本
//            'key_action_rate' => 'game_addiction_rate' // 应用下载广告数据-关键行为率
        ];
    }

    /**
     * 统一报表字段
     * @param array $data
     * @param bool $isNew
     * @return array
     */
    private function reportFieldsFormat(array $data = [], bool $isNew = false)
    {
        if (empty($data)) {
            return [];
        }

        $reportFields = array_flip($this->reportFields($isNew));
        $format = [];

        foreach ($data as $k => $v) {
            if (isset($reportFields[$k])) {
                $format[$reportFields[$k]] = $v;
            }
        }

        $format['avg_show_cost'] = $format['show'] > 0 ? round(($format['cost'] ?? 0) * 1000 / $format['show'], 4) : 0;
        $format['avg_click_cost'] = $format['click'] > 0 ? round(($format['cost'] ?? 0) / $format['click'], 4) : 0;
        $format['click_rate'] = $format['show'] > 0 ? round(($format['click'] ?? 0) * 100 / $format['show'], 4) : 0;
        $format['active_cost'] = $format['active'] > 0 ? round(($format['cost'] ?? 0) / $format['active'], 4) : 0;
        $format['next_day_open_cost'] = $format['next_day_open'] > 0 ? round(($format['cost'] ?? 0) / $format['next_day_open'], 4) : 0;
        $format['next_day_open_rate'] = $format['active'] > 0 ? round(($format['next_day_open'] ?? 0) * 100 / $format['active'], 4) : 0;
        $format['key_action_cost'] = $format['key_action'] > 0 ? round(($format['cost'] ?? 0) / $format['key_action'], 4) : 0;
        $format['key_action_rate'] = $format['active'] > 0 ? round(($format['key_action'] ?? 0) / $format['active'], 4) : 0;

        return $format;
    }

    /**
     * 统一分页信息
     * @param array $data
     * @return array
     */
    private function pageInfoFormat(array $data)
    {
        return [
            'page' => $data['pageIndex'],
            'page_size' => $data['pageSize'],
            'total_number' => $data['rowCount'],
            'total_page' => $data['pageCount']
        ];
    }

    public function authAccountGet()
    {
        // TODO: Implement authAccountGet() method.
    }

    public function reportProjectGet(string $advertiserId, string $date)
    {
        // TODO: Implement reportProjectGet() method.
    }

    public function reportAdGet(string $advertiserId, string $date)
    {
        // TODO: Implement reportAdGet() method.
    }
}