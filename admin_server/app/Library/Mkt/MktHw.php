<?php

declare(strict_types=1);

namespace App\Library\Mkt;

use App\Utils\Log;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MktHw extends MktAbstract
{
    protected $baseUri = 'https://ads.cloud.huawei.com';

    protected $authBaseUri = 'https://oauth-login.cloud.huawei.com';

    /**
     * @param string $uri
     * @param string $method
     * @param array $params
     * @param array $headers
     * @return array
     * @throws Exception
     */
    public function request(string $uri = '', string $method = 'GET', array $params = [], array $headers = [])
    {
        $field = strtoupper($method) == 'POST' ? 'json' : 'query';
        $options = [
            'headers' => $headers,
            $field => $params
        ];

        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MKT-HW】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        if (($content['code'] ?? -1) !== '0') {
            Log::http('info', '【MKT-HW】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
            throw new Exception($content['message'] ?? '响应异常');
        }

        return (array)$content['data'];
    }

    /**
     * @param string $uri
     * @param string $method
     * @param array $params
     * @param array $headers
     * @return array
     * @throws Exception
     */
    public function accountRequest(string $uri = '', string $method = 'GET', array $params = [], array $headers = [])
    {
        $field = strtoupper($method) == 'POST' ? 'json' : 'query';
        $options = [
            'headers' => $headers,
            $field => $params
        ];

        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MKT-HW】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        if (($content['code'] ?? -1) !== '200') {
            Log::http('info', '【MKT-HW】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
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
        return $this->request($api, $method, $params, array_merge($headers, ['Authorization' => $this->getToken()]));
    }

    /**
     * @param string $api
     * @param string $method
     * @param array $params
     * @param array $headers
     * @return array
     * @throws Exception
     */
    public function accountRequestByToken(string $api = '', string $method = 'GET', array $params = [], array $headers = [])
    {
        return $this->accountRequest($api, $method, $params, array_merge($headers, ['Authorization' => $this->getToken()]));
    }

    /**
     * @param string $authCode
     * @return array
     * @throws Exception
     */
    public function authAccessToken(string $authCode)
    {
        parse_str(parse_url($this->getAppInfo()['auth_url'] ?? '')['query'] ?? '', $query);
        $redirectUri = $query['redirect_uri'] ?? '';
        $data = $this->authRequest('/oauth2/v2/token', 'POST', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->getAppId(),
            'client_secret' => $this->getAppSecret(),
            'code' => $authCode,
            'redirect_uri' => $redirectUri
        ]);

        if (empty($data['access_token'])) {
            throw new Exception(__CLASS__ . ':' . __FUNCTION__ . '响应异常');
        }

        return [
            'app_id' => $this->getAppId(),
            'access_token' => $data['token_type'] . ' ' . $data['access_token'],
            'access_time' => time() + $data['expires_in'],
            'refresh_token' => $data['refresh_token'],
        ];
    }

    /**
     * @param string $refreshToken
     * @return array
     * @throws Exception
     */
    public function authRefreshToken(string $refreshToken)
    {
        $data = $this->authRequest('/oauth2/v2/token', 'POST', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->getAppId(),
            'client_secret' => $this->getAppSecret(),
            'refresh_token' => $refreshToken
        ]);

        if (empty($data['access_token'])) {
            throw new Exception(__CLASS__ . ':' . __FUNCTION__ . '响应异常');
        }

        return [
            'app_id' => $this->getAppId(),
            'access_token' => $data['token_type'] . ' ' . $data['access_token'],
            'access_time' => time() + $data['expires_in'],
            'refresh_token' => $data['refresh_token'],
        ];
    }

    /**
     * @param string $uri
     * @param string $method
     * @param array $params
     * @param array $headers
     * @return array
     * @throws Exception
     */
    protected function authRequest(string $uri = '', string $method = 'GET', array $params = [], array $headers = [])
    {
        $field = strtoupper($method) == 'POST' ? 'form_params' : 'query';
        $options = [
            'headers' => $headers,
            $field => $params
        ];

        try {
            $response = $this->getClient($this->authBaseUri)->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MKT-HW】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        return (array)json_decode($response->getBody()->getContents(), true);
    }

    public function authUserInfo()
    {
        // TODO: Implement authUserInfo() method.
    }

    public function authAccountGet()
    {
        // TODO: Implement authAccountGet() method.
    }

    /**
     * 获取帐户下广告主列表
     * @param string $accountId
     * @param int $page
     * @param int $pageSize
     * @param array $list
     * @return array
     * @throws Exception
     */
    public function advertiserGet(string $accountId, int $page = 1, int $pageSize = 50, $list = [])
    {
        $data = $this->accountRequestByToken('/openapi/v2/manager/advertiser/query', 'GET', [
            'manager_id' => $accountId,
            'page_num' => $page,
            'page_size' => $pageSize,
        ]);

        foreach (($data['advertiser_info_list'] ?? []) as $v) {
            $list[] = [
                'account_id' => $accountId,
                'advertiser_id' => $v['advertiser_id'],
                'advertiser_name' => $v['nick_name'],
            ];
        }

        if ($data['total'] > $page * $pageSize) {
            return $this->advertiserGet($accountId, $page++, $pageSize, $list);
        }

        return $list;
    }

    /**
     * 计划列表
     * @param string $advertiserId
     * @param int $page
     * @param int $pageSize
     * @param array $planIds
     * @return array
     * @throws Exception
     */
    public function planGet(string $advertiserId, int $page = 1, int $pageSize = 100, array $planIds = [])
    {
        $pageSize = $pageSize < 1 ? 1 : ($pageSize > 100 ? 100 : $pageSize);
        $page = $page < 1 ? 1 : $page;

        $params = [
            'advertiser_id' => $advertiserId,
            'page_num' => $page,
            'page_size' => $pageSize,
        ];

        if (!empty($planIds)) {
            $params['campaign_ids'] = $planIds;
        }

        $data = $this->accountRequestByToken('/openapi/v2/promotion/campaign/query', 'GET', $params);

        $putStatus = [
            'OPERATION_STATUS_ENABLE' => 1,
            'OPERATION_STATUS_DISABLE' => 2,
            'OPERATION_STATUS_DELETE' => 3,
        ];

        $list = [];
        foreach ($data['data'] as $v) {
            $list[] = [
                'plan_id' => $v['campaign_id'],
                'plan_name' => $v['campaign_name'],
                'advertiser_id' => $advertiserId,
                'put_status' => $putStatus[$v['campaign_status']] ?? 0,
                'budget_mode' => $v['budget_mode'] ?? '',
                'budget_amount' => round($v['today_daily_budget'], 4),
//                'schedule_start' => $v['begin_time'],
//                'schedule_end' => $v['end_time'],
//                'schedule_time' => $v['schedule_time'],
//                'update_time' => $v['update_time'],
                'create_time' => $v['created_time'],
            ];
        }

        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($page, $pageSize, $data['total'])
        ];
    }

    /**
     *
     * @param string $advertiserId
     * @param string $date
     * @param string $granularity
     * @return array
     * @throws Exception
     */
    public function reportAdvertiserGet(string $advertiserId, string $date, $granularity = 'daily')
    {
        $timeGranularity = strtoupper($granularity) == 'DAILY' ? 'DAILY' : 'HOURLY';

        $date = date('Y-m-d', strtotime($date));

        $data = $this->requestByToken('/openapi/v2/reports/advertiser/query', 'POST', [
            'advertiser_id' => $advertiserId,
            'start_date' => $date,
            'end_date' => $date,
            'time_granularity' => 'STAT_TIME_GRANULARITY_' . $timeGranularity,// 时间粒度
            'group_by' => [
                'ADVERTISER_ID'
            ],
            'index_screen_list' => [
                [
                    'index_screen' => 'effective_cost',
                    'type' => 1,
                    'low_value' => 0.01
                ]
            ],
            'page' => 1,
            'page_size' => 1000,
        ]);

        $list = [];
        foreach ($data['list'] as $v) {
            $d = $this->reportFieldsFormat($v);

            $d['date'] = $date;
            $d['advertiser_id'] = $advertiserId;

            if ($timeGranularity == 'HOURLY') {
                $d['hour'] = date('H', strtotime($v['stat_datetime'] . '00'));
            }

            $list[] = $d;
        }

        return $list;
    }

    /**
     * 报表-计划
     * @param string $advertiserId
     * @param string $date
     * @param array $planIds
     * @param string $granularity
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws Exception
     */
    public function reportPlanGet(string $advertiserId, string $date, array $planIds = [], $granularity = 'daily', int $page = 1, int $pageSize = 100)
    {
        $timeGranularity = strtoupper($granularity) == 'DAILY' ? 'DAILY' : 'HOURLY';

        $date = date('Y-m-d', strtotime($date));

        $params = [
            'advertiser_id' => $advertiserId,
            'start_date' => $date,
            'end_date' => $date,
            'time_granularity' => 'STAT_TIME_GRANULARITY_' . $timeGranularity,// 时间粒度
            'group_by' => [
                'CAMPAIGN_ID'
            ],
            'index_screen_list' => [
                [
                    'index_screen' => 'effective_cost',
                    'type' => 1,
                    'low_value' => 0.01
                ]
            ],
            'page' => $page,
            'page_size' => $pageSize,
        ];

        if (!empty($planIds)) {
            $params['filtering'] = [
                'campaign_ids' => $planIds
            ];
        }

        $data = $this->requestByToken('/openapi/v2/reports/campaign/query', 'POST', $params);


        $list = [];
        foreach ($data['list'] as $v) {
            $d = $this->reportFieldsFormat($v);

            $d['date'] = $date;
            $d['advertiser_id'] = $advertiserId;
            $d['plan_id'] = $v['campaign_id'];

            if ($timeGranularity == 'HOURLY') {
                $d['hour'] = date('H', strtotime($v['stat_datetime'] . '00'));
            }

            $list[] = $d;
        }

        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($page, $pageSize, $data['page_info']['total_num'])
        ];

        $data = $this->requestByToken('/openapi/v2/reports/campaign/query', 'POST', $params);

        $list = [];
        foreach ($data['details'] as $v) {
            $d = $this->reportFieldsFormat($v);

            $d['date'] = $date;
            $d['advertiser_id'] = $advertiserId;
            $d['plan_id'] = $v['unit_id'];
            $d['hour'] = date('H', strtotime($date . ' ' . $v['stat_hour'] . ':00:00'));

            $list[] = $d;
        }
        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($page, $pageSize, $data['total_count'])
        ];
    }

    /**
     * 统一分页信息
     * @param int $page
     * @param int $pageSize
     * @param int $totalNumber
     * @return array
     */
    private function pageInfoFormat(int $page, int $pageSize, int $totalNumber)
    {
        return [
            'page' => $page,
            'page_size' => $pageSize,
            'total_number' => $totalNumber,
            'total_page' => ceil($totalNumber / $pageSize)
        ];
    }

    /**
     * 报表数据字短
     * @return array
     */
    private function reportFields()
    {
        return [
            'cost' => 'cost',// 展现数据-总花费
            'show' => 'show_count',// 展现数据-素材曝光
            'avg_show_cost' => 'thousand_show_cost',// 展现数据-平均千次展现费用
            'click' => 'click_count',// 展现数据-点击数
            'avg_click_cost' => 'cpc',// 展现数据-平均行为单价
//            'click_rate' => 'action_ratio',// 展现数据-行为率
            'convert' => 'app_custom_count',// 转化数据-转化数
            'convert_cost' => 'app_custom_cost',// 转化数据-转化成本
//            'convert_rate' => 'click_conversion_ratio',// 转化数据-转化率
            //'deep_convert', // 转化数据-深度转化数
            //'deep_convert_cost', // 转化数据-深度转化成本
            //'deep_convert_rate', // 转化数据-深度转化率
            //'install_finish',// 应用下载广告数据-安卓安装完成数
            'active' => 'active_count',// 应用下载广告数据-激活数
            'active_cost' => 'active_cost', // 应用下载广告数据-激活成本
//            'active_rate' => 'conversion_ratio', // 应用下载广告数据-激活率
            'next_day_open' => 'retain_count',// 应用下载广告数据-次留数
            'next_day_open_cost' => 'retain_cost',// 应用下载广告数据-次留成本
//            'next_day_open_rate' => 'event_next_day_stay_ratio',// 应用下载广告数据-次留率
            'key_action' => 'app_custom_count',// 应用下载广告数据-关键行为数
            'key_action_cost' => 'app_custom_cost', // 应用下载广告数据-关键行为成本
//            'key_action_rate' => 'key_action_ratio' // 应用下载广告数据-关键行为率
        ];
    }

    /**
     * 统一报表字段
     * @param array $data
     * @return array
     */
    private function reportFieldsFormat($data = array())
    {
        if (empty($data)) {
            return [];
        }

        $reportFields = array_flip($this->reportFields());
        $format = [];
        // TODO 比例数据是否需要特殊处理
        foreach ($data as $k => $v) {
            if (isset($reportFields[$k])) {
                // 百分比转化
                $format[$reportFields[$k]] = strstr($reportFields[$k], 'rate') ? $v * 100 : $v;
            }
        }
        // 千次曝光
//        $format['avg_show_cost'] = ($format['cost'] ?? 0) > 0 && ($format['show'] ?? 0) > 0 ? round(($format['cost'] ?? 0) * 1000 / ($format['show'] ?? 0), 2) : 0;
        // 转化率百分比
        $format['convert_rate'] = ($format['convert'] ?? 0) > 0 && ($format['click'] ?? 0) > 0 ? round(($format['convert'] ?? 0) * 1000 / ($format['click'] ?? 0), 2) : 0;
        return $format;
    }

    public function reportProjectGet(string $advertiserId, string $date)
    {
        // TODO: Implement reportProjectGet() method.
    }

    public function reportAdGet(string $advertiserId, string $date)
    {
        // TODO: Implement reportAdGet() method.
    }

    public function planUpdate(string $advertiserId, string $planId, array $params = [])
    {
        // TODO: Implement planUpdate() method.
    }

    public function planUpdateStatus(string $advertiserId, string $planId, string $putStatus)
    {
        // TODO: Implement planUpdateStatus() method.
    }

    public function planUpdateBudget(string $advertiserId, string $planId, float $budgetAmount)
    {
        // TODO: Implement planUpdateBudget() method.
    }
}