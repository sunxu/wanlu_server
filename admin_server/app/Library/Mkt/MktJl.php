<?php

declare(strict_types=1);

namespace App\Library\Mkt;

use App\Model\Mkt\MktAccount;
use App\Utils\Log;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MktJl extends MktAbstract
{
    protected $baseUri = 'https://ad.oceanengine.com';

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

        $start_time = microtime(true);
        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MKT-JL】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        if (($content['code'] ?? -1) !== 0) {
            Log::http('info', '【MKT-JL】',
                [
                    'uri' => $uri,
                    'options' => $options,
                    'response' => $content,
                    'stack' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
                ]);

            if (($content['code'] ?? -1) == 40110) {
                sleep(1);
            }

            throw new Exception($content['message'] ?? '响应异常');
        }

        Log::http('info', '【MKT-JL】',
            [
                'uri' => $uri,
                'options' => $options,
                'content_code' => $content['code'],
                'call_time' => microtime(true) - $start_time,
            ]);

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
        return $this->request($api, $method, $params, array_merge($headers, ['Access-Token' => $this->getToken()]));
    }

    /**
     * 获取Token
     * @param string $authCode
     * @return array
     * @throws Exception
     */
    public function authAccessToken(string $authCode)
    {
        $data = $this->request('/open_api/oauth2/access_token/', 'POST', [
            'app_id' => $this->getAppId(),
            'secret' => $this->getAppSecret(),
            'grant_type' => 'auth_code',
            'auth_code' => $authCode
        ]);

        if (empty($data['access_token'])) {
            throw new Exception(__CLASS__ . ':' . __FUNCTION__ . '响应异常');
        }

        return [
            'app_id' => $this->getAppId(),
            'access_token' => $data['access_token'],
            'access_time' => time() + $data['expires_in'],
            'refresh_token' => $data['refresh_token'],
            'refresh_time' => time() + $data['refresh_token_expires_in'],
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
        $data = $this->request('/open_api/oauth2/refresh_token/', 'POST', [
            'app_id' => $this->getAppId(),
            'secret' => $this->getAppSecret(),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken
        ]);

        if (empty($data['access_token'])) {
            throw new Exception(__CLASS__ . ':' . __FUNCTION__ . '响应异常');
        }

        return [
            'app_id' => $this->getAppId(),
            'access_token' => $data['access_token'],
            'access_time' => time() + $data['expires_in'],
            'refresh_token' => $data['refresh_token'],
            'refresh_time' => time() + $data['refresh_token_expires_in'],
        ];
    }

    /**
     * 授权用户信息
     * @return array
     * @throws Exception
     */
    public function authUserInfo()
    {
        $data = $this->requestByToken('/open_api/2/user/info/', 'GET');

        if (empty($data['id'])) {
            throw new Exception(__CLASS__ . ':' . __FUNCTION__ . '响应异常');
        }

        return [
            'user_id' => $data['id'],
            'user_email' => $data['email'] ?? '',
            'user_name' => $data['display_name'] ?? ''
        ];
    }

    /**
     * 获取授权帐户
     * @return array
     * @throws Exception
     */
    public function authAccountGet()
    {
        $data = $this->request('/open_api/oauth2/advertiser/get/', 'GET', [
            'app_id' => $this->getAppId(),
            'secret' => $this->getAppSecret(),
            'access_token' => $this->getToken()
        ]);

        if (empty($data['list'])) {
            throw new Exception(__CLASS__ . ':' . __FUNCTION__ . '响应异常');
        }

        $list = [];
        foreach ($data['list'] as $v) {
            $list[] = [
                'account_id' => $v['advertiser_id'],
                'account_name' => $v['advertiser_name'],
                'account_role' => $v['account_role'],
                'is_valid' => $v['is_valid'] ? 1 : 0,
            ];
        }
        return $list;
    }

    /**
     * 获取帐户下广告主列表
     * @param string $accountId
     * @return array
     * @throws Exception
     */
    public function advertiserGet(string $accountId)
    {
        $account_role_list = MktAccount::query()->where('account_id', $accountId)
            ->where('is_valid', 1)->pluck('account_role')->toArray();

        $list = [];
        if (current($account_role_list) == 'PLATFORM_ROLE_ENTERPRISE_BP_ADMIN') {
            $list = $this->advertiserGet_NEW($accountId);
        }
        if (current($account_role_list) == 'CUSTOMER_ADMIN') {
            $list = $this->advertiserGet_ORIG($accountId);
        }

        Log::http('info', '【MKT-JL】',
            [
                'func' => 'advertiserGet',
                'account_id' => $accountId,
                'account_role' => $account_role_list,
                'mkt_advertiser' => $list,
            ]);

        return $list;
    }

    public function advertiserGet_ORIG(string $accountId)
    {
        $data = $this->requestByToken('/open_api/2/majordomo/advertiser/select/', 'GET', [
            'advertiser_id' => $accountId
        ]);

        $list = [];
        foreach (($data['list'] ?? []) as $v) {
            $list[] = [
                'account_id' => $accountId,
                'advertiser_id' => $v['advertiser_id'],
                'advertiser_name' => $v['advertiser_name'],
            ];
        }

        return $list;
    }

    public function advertiserGet_NEW(string $accountId) {
        $total_page = 100;
        $current_page = 1;
        $list = [];

        while ($current_page <= $total_page) {
            $data = $this->requestByToken('/open_api/2/ebp/advertiser/list/', 'GET', [
                'enterprise_organization_id' => $accountId,
                'account_source' => 'AD',
                'page' => $current_page,
                'page_size' => 100,
            ]);
            foreach (($data['account_list'] ?? []) as $v) {
                $list[] = [
                    'account_id' => $accountId,
                    'advertiser_id' => $v['account_id'],
                    'advertiser_name' => $v['account_name'],
                ];
            }

            $total_page = intval($data['page_info']['total_page']);
            $current_page++;
        }

        return $list;
    }


    /**
     * 帐户日预算
     * @param array $advertiserIds
     * @return array
     * @throws Exception
     */
    public function advertiserBudget(array $advertiserIds)
    {
        $data = $this->requestByToken('/open_api/2/advertiser/budget/get/', 'GET', [
            'advertiser_ids' => $advertiserIds
        ]);

        $list = [];
        foreach (($data['list'] ?? []) as $v) {
            $list[] = [
                'advertiser_id' => $v['advertiser_id'],
                'budget' => $v['budget'],
                'date' => date('Y-m-d')
            ];
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
        return $this->reportAdvertiserGet_V3($advertiserId, $date, $granularity);

        $timeGranularity = sprintf('STAT_TIME_GRANULARITY_%s', strtoupper($granularity) == 'DAILY' ? 'DAILY' : 'HOURLY');

        $date = date('Y-m-d', strtotime($date));
        $data = $this->requestByToken('/open_api/2/report/advertiser/get/', 'GET', [
            'advertiser_id' => $advertiserId,
            'start_date' => $date,
            'end_date' => $date,
            'fields' => array_values($this->reportFields()),
            'time_granularity' => $timeGranularity,// 时间粒度
            'group_by' => [
                'STAT_GROUP_BY_FIELD_ID',
                'STAT_GROUP_BY_FIELD_STAT_TIME'
            ],
            'page' => 1,
            'page_size' => 1000,
        ]);

        $list = [];
        foreach ($data['list'] as $v) {
            $d = $this->reportFieldsFormat($v);
            $d['date'] = $date;
            $d['hour'] = date('H', strtotime($v['stat_datetime']));
            $d['advertiser_id'] = $advertiserId;
            $list[] = $d;
        }

        return $list;
    }

    public function reportAdvertiserGet_V3(string $advertiserId, string $date, $granularity = 'daily', int $page = 1, int $pageSize = 100) {

        $granularity = $granularity == 'daily' ? 'stat_time_day' : 'stat_time_hour';
        $fieldId = 'cdp_promotion_id';
        $dimensions = [
//            'cdp_project_id',
            $granularity,
//            $fieldId
        ];

        $filters = [
            [
                'field' => 'stat_cost',
                'type' => 3,// 字段类型 1-固定枚举值 2-固定输入值 3-数值类型
                'operator' => 4,// 处理方式。 允许值：1-等于 2-小于 3-小于等于 4-大于 5-大于等于 6-不等于 7-包含 8-不包含 9-范围查询 10-多个值匹配包含 11-多个值匹配都要包含
                'values' => ['0']
            ]
        ];

        $this->log(__LINE__, "reportCustom request", [
            $advertiserId, $date, $dimensions, $filters, $page, $pageSize
        ]);
        $data = $this->reportCustom($advertiserId, $date, $dimensions, $filters, $page, $pageSize);
        $this->log(__LINE__, "reportCustom response", $data);

        $list = [];
        foreach ($data['rows'] ?? [] as $v) {
            $d = $this->reportFieldsFormat($v['metrics'] ?? [], true);
            $d['date'] = $date;
            $d['hour'] = date('H', strtotime(explode(' - ', $v['dimensions'][$granularity])[0]));
            $d['advertiser_id'] = $advertiserId;
            $list[] = $d;
        }

        $this->log(__LINE__, "reportAdvertiserGet_V3 return", $list);
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
            'page' => $page,
            'page_size' => $pageSize,
        ];

        if (!empty($ids)) {
            $params['filtering'] = [
                'ad_ids' => $ids
            ];
        }

        $data = $this->requestByToken('/open_api/2/ad/get/', 'GET', $params);

        $putStatus = [
            'AD_STATUS_ENABLE' => 1,
            'AD_STATUS_DISABLE' => 2,
            'AD_STATUS_DELETE' => 3,
        ];
        $list = [];
        foreach ($data['list'] as $v) {
            $list[] = [
                'plan_id' => $v['id'],
                'plan_name' => $v['name'],
                'advertiser_id' => $v['advertiser_id'],
                'put_status' => $putStatus[$v['opt_status']] ?? ($putStatus[$v['status']] ?? 0),
                'budget_mode' => $v['budget_mode'],
                'budget_amount' => $v['budget'],
                'schedule_start' => $v['start_time'],
                'schedule_end' => $v['end_time'],
                'schedule_time' => $v['schedule_time'],
                'update_time' => $v['ad_modify_time'],
                'create_time' => $v['ad_create_time'],
            ];
        }

        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($data['page_info'])
        ];
    }

    /**
     * 广告列表
     * @param string $advertiserId
     * @param int $page
     * @param int $pageSize
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function adGet(string $advertiserId, int $page = 1, int $pageSize = 10, array $ids = [])
    {
        $pageSize = $pageSize < 1 ? 1 : ($pageSize > 10 ? 10 : $pageSize);
        $page = $page < 1 ? 1 : $page;

        $params = [
            'advertiser_id' => $advertiserId,
            'page' => $page,
            'page_size' => $pageSize,
        ];

        if (!empty($ids)) {
            $params['filtering'] = [
                'ids' => $ids
            ];
        }

        $data = $this->requestByToken('open_api/v3.0/promotion/list/', 'GET', $params);

        $putStatus = [
            'ENABLE' => 1,
            'DISABLE' => 2,
            'DELETE' => 3,
        ];
        $list = [];
        foreach ($data['list'] as $v) {
            $d = [
                'ad_id' => $v['promotion_id'],
                'ad_name' => $v['promotion_name'] ?? '',
                'advertiser_id' => $v['advertiser_id'] ?? $advertiserId,
                'put_status' => $putStatus[$v['opt_status']] ?? ($putStatus[$v['status']] ?? 0),
                'budget_amount' => $v['budget'] ?? 0,
                'update_time' => $v['promotion_modify_time'],
                'create_time' => $v['promotion_create_time'],
            ];
            if (!empty($v['project_id'])) {
                $d['project_id'] = $v['project_id'];
            }
            $list[] = $d;
        }

        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($data['page_info'])
        ];
    }

    /**
     * 项目列表
     * @param string $advertiserId
     * @param int $page
     * @param int $pageSize
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function projectGet(string $advertiserId, int $page = 1, int $pageSize = 100, array $ids = [])
    {
        $pageSize = $pageSize < 1 ? 1 : ($pageSize > 100 ? 100 : $pageSize);
        $page = $page < 1 ? 1 : $page;

        $params = [
            'advertiser_id' => $advertiserId,
            'page' => $page,
            'page_size' => $pageSize,
        ];

        if (!empty($ids)) {
            $params['filtering'] = [
                'ids' => $ids
            ];
        }

        $data = $this->requestByToken('open_api/v3.0/project/list/', 'GET', $params);

        $putStatus = [
            'ENABLE' => 1,
            'DISABLE' => 2,
            'DELETE' => 3,
        ];
        $list = [];
        foreach ($data['list'] as $v) {
            $list[] = [
                'project_id' => $v['project_id'],
                'project_name' => $v['name'] ?? '',
                'advertiser_id' => $v['advertiser_id'],
                'put_status' => $putStatus[$v['opt_status']] ?? ($putStatus[$v['status']] ?? 0),
                'budget_mode' => $v['delivery_setting']['budget_mode'] ?? '',
                'budget_amount' => $v['delivery_setting']['budget'] ?? 0,
                'schedule_start' => $v['delivery_setting']['start_time'],
                'schedule_end' => $v['delivery_setting']['end_time'],
                'schedule_time' => $v['delivery_setting']['schedule_time'],
                'update_time' => $v['project_modify_time'],
                'create_time' => $v['project_create_time'],
            ];
        }

        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($data['page_info'])
        ];
    }


    /**
     * @param string $advertiserId
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    public function adBatchUpdateStatus(string $advertiserId, array $data = [])
    {
        $list = [];
        foreach ($data as $v) {
            $list [] = [
                'promotion_id' => intval($v['ad_id']),
                'opt_status' => strtoupper($v['status']),
            ];
        }
        $data = $this->requestByToken('/open_api/v3.0/promotion/status/update/', 'POST', [
            'advertiser_id' => intval($advertiserId),
            'data' => $list
        ]);

        return json_decode(str_replace('promotion_id', 'ad_id', json_encode($data)), true);
    }

    /**
     * 报表-项目
     * @param string $advertiserId
     * @param string $date
     * @param array $projectIds
     * @param string $granularity
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws Exception
     */
    public function reportProjectGet(string $advertiserId, string $date, array $projectIds = [], $granularity = 'daily', int $page = 1, int $pageSize = 100)
    {
        $granularity = $granularity == 'daily' ? 'stat_time_day' : 'stat_time_hour';
        $fieldId = 'cdp_project_id';
        $dimensions = [
            $granularity,
            $fieldId
        ];

        $filters = [
            [
                'field' => 'stat_cost',
                'type' => 3,// 字段类型 1-固定枚举值 2-固定输入值 3-数值类型
                'operator' => 4,// 处理方式。 允许值：1-等于 2-小于 3-小于等于 4-大于 5-大于等于 6-不等于 7-包含 8-不包含 9-范围查询 10-多个值匹配包含 11-多个值匹配都要包含
                'values' => ['0']
            ]
        ];

        $data = $this->reportCustom($advertiserId, $date, $dimensions, $filters, $page, $pageSize);

        $list = [];
        foreach ($data['rows'] ?? [] as $v) {
            $d = $this->reportFieldsFormat($v['metrics'] ?? [], true);
            $d['date'] = $date;
            $d['hour'] = date('H', strtotime(explode(' - ', $v['dimensions'][$granularity])[0]));
            $d['advertiser_id'] = $advertiserId;
            $d['project_id'] = $v['dimensions'][$fieldId];
            $list[] = $d;
        }
        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($data['page_info']),// page、page_size、total_number、total_page
        ];

        // 体验接口停用
        $timeGranularity = sprintf('STAT_TIME_GRANULARITY_%s', strtoupper($granularity) == 'DAILY' ? 'DAILY' : 'HOURLY');

        $date = date('Y-m-d', strtotime($date));

        $params = [
            'advertiser_id' => $advertiserId,
            'start_date' => $date,
            'end_date' => $date,
            'fields' => array_values($this->reportFields(true)),
            'time_granularity' => $timeGranularity,// 时间粒度
            'page' => $page,
            'page_size' => $pageSize,
        ];

        if (!empty($projectIds)) {
            $params['filtering'] = [
                'project_id' => array_map('intval', $projectIds)
            ];
        }

        $data = $this->requestByToken('open_api/v3.0/report/project/get/', 'GET', $params);

        $list = [];
        foreach ($data['list'] ?? [] as $v) {
            $d = $this->reportFieldsFormat($v['data'][0] ?? [], true);
            $d['date'] = $date;
            $d['hour'] = date('H', strtotime(explode(' - ', $v['stat_datetime'])[0]));
            $d['advertiser_id'] = $advertiserId;
            $d['project_id'] = $v['project_id'];
            $list[] = $d;
        }

        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($data['page_info']),// page、page_size、total_number、total_page
        ];
    }

    /**
     * 报表-广告
     * @param string $advertiserId
     * @param string $date
     * @param array $adIds
     * @param string $granularity
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws Exception
     */
    public function reportAdGet(string $advertiserId, string $date, array $adIds = [], $granularity = 'daily', int $page = 1, int $pageSize = 100)
    {
        $granularity = $granularity == 'daily' ? 'stat_time_day' : 'stat_time_hour';
        $fieldId = 'cdp_promotion_id';
        $dimensions = [
            'cdp_project_id',
            $granularity,
            $fieldId
        ];

        $filters = [
            [
                'field' => 'stat_cost',
                'type' => 3,// 字段类型 1-固定枚举值 2-固定输入值 3-数值类型
                'operator' => 4,// 处理方式。 允许值：1-等于 2-小于 3-小于等于 4-大于 5-大于等于 6-不等于 7-包含 8-不包含 9-范围查询 10-多个值匹配包含 11-多个值匹配都要包含
                'values' => ['0']
            ]
        ];

        $data = $this->reportCustom($advertiserId, $date, $dimensions, $filters, $page, $pageSize);

        $list = [];
        foreach ($data['rows'] ?? [] as $v) {
            $d = $this->reportFieldsFormat($v['metrics'] ?? [], true);
            $d['date'] = $date;
            $d['hour'] = date('H', strtotime(explode(' - ', $v['dimensions'][$granularity])[0]));
            $d['advertiser_id'] = $advertiserId;
            $d['ad_id'] = $v['dimensions'][$fieldId];
            $d['project_id'] = $v['dimensions']['cdp_project_id'];
            $list[] = $d;
        }
        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($data['page_info']),// page、page_size、total_number、total_page
        ];

        // 就版接口停用
        $timeGranularity = sprintf('STAT_TIME_GRANULARITY_%s', strtoupper($granularity) == 'DAILY' ? 'DAILY' : 'HOURLY');

        $date = date('Y-m-d', strtotime($date));

        $params = [
            'advertiser_id' => $advertiserId,
            'start_date' => $date,
            'end_date' => $date,
            'fields' => array_values($this->reportFields(true)),
            'time_granularity' => $timeGranularity,// 时间粒度
            'page' => $page,
            'page_size' => $pageSize,
        ];

        if (!empty($adIds)) {
            $params['filtering'] = [
                'promotion_id' => array_map('intval', $adIds)
            ];
        }

        $data = $this->requestByToken('open_api/v3.0/report/promotion/get/', 'GET', $params);

        $list = [];
        foreach ($data['list'] ?? [] as $v) {
            $d = $this->reportFieldsFormat($v['data'][0] ?? [], true);
            $d['date'] = $date;
            $d['hour'] = date('H', strtotime(explode(' - ', $v['stat_datetime'])[0]));
            $d['advertiser_id'] = $advertiserId;
            $d['ad_id'] = $v['promotion_id'];
            $d['project_id'] = $v['project_id'];
            $list[] = $d;
        }

        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($data['page_info']),// page、page_size、total_number、total_page
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
     * @return array
     * @throws Exception
     */
    public function reportPlanGet(string $advertiserId, string $date, array $planIds = [], $granularity = 'daily', int $page = 1, int $pageSize = 100)
    {
        $timeGranularity = sprintf('STAT_TIME_GRANULARITY_%s', strtoupper($granularity) == 'DAILY' ? 'DAILY' : 'HOURLY');

        $date = date('Y-m-d', strtotime($date));

        $params = [
            'advertiser_id' => $advertiserId,
            'start_date' => $date,
            'end_date' => $date,
            'fields' => array_values($this->reportFields()),
            'time_granularity' => $timeGranularity,// 时间粒度
            'group_by' => [
                'STAT_GROUP_BY_FIELD_ID',
                'STAT_GROUP_BY_FIELD_STAT_TIME'
            ],
            'page' => $page,
            'page_size' => $pageSize,
        ];

        if (!empty($planIds)) {
            $params['filtering'] = [
                'ad_ids' => $planIds
            ];
        }

        $data = $this->requestByToken('/open_api/2/report/ad/get/', 'GET', $params);

        $list = [];
        foreach ($data['list'] as $v) {
            $d = $this->reportFieldsFormat($v);
            $d['date'] = $date;
            $d['hour'] = date('H', strtotime($v['stat_datetime']));
            $d['advertiser_id'] = $advertiserId;
            $d['plan_id'] = $v['ad_id'];
            $list[] = $d;
        }

        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($data['page_info']),// page、page_size、total_number、total_page
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
        $data = $this->requestByToken('/open_api/2/ad/update/', 'POST', array_merge([
            'advertiser_id' => $advertiserId,
            'ad_id' => $planId,
        ], $params));

        return $planId == $data['ad_id'];
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
        $data = $this->requestByToken('/open_api/2/ad/update/status/', 'POST', [
            'advertiser_id' => $advertiserId,
            'ad_ids' => [$planId],
            'opt_status' => $putStatus,
        ]);

        return in_array($planId, $data['ad_ids']);
    }

    /**
     * 广告更新状态
     * @param string $advertiserId
     * @param string $adId
     * @param string $putStatus
     * @return bool
     * @throws Exception
     */
    public function adUpdateStatus(string $advertiserId, string $adId, string $putStatus = 'enable')
    {
        $data = $this->requestByToken('/open_api/v3.0/promotion/status/update/', 'POST', [
            'advertiser_id' => (int)$advertiserId,
            'data' => [
                [
                    'promotion_id' => (int)$adId,
                    'opt_status' => strtoupper($putStatus)
                ]
            ]
        ]);

        return in_array($adId, $data['promotion_ids'] ?? []);
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
        $data = $this->requestByToken('/open_api/2/ad/update/budget/', 'POST', [
            'advertiser_id' => $advertiserId,
            'data' => [
                [
                    'ad_id' => $planId,
                    'budget' => $budgetAmount,
                ]
            ],
        ]);

        return in_array($planId, $data['ad_ids']);
    }

    /**
     * 自定义报表
     * @param string $advertiserId
     * @param string $date
     * @param array $dimensions
     * @param array $filters
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws Exception
     */
    public function reportCustom(string $advertiserId, string $date, array $dimensions = [], $filters = [], int $page = 1, int $pageSize = 100)
    {
        $date = date('Y-m-d', strtotime($date));

        $params = [
            'advertiser_id' => $advertiserId,
            'data_topic' => 'BASIC_DATA',
            'dimensions' => $dimensions,
            'start_time' => $date,
            'end_time' => $date,
            'metrics' => array_values($this->reportFields(true)),
            'filters' => $filters,
            'order_by' => [
                [
                    'field' => 'stat_cost',
                    'type' => 'DESC'
                ]
            ],
            'page' => $page,
            'page_size' => $pageSize,
        ];

        return $this->requestByToken('/open_api/v3.0/report/custom/get/', 'GET', $params);
    }

    /**
     * 自定义报表可用指标和维度
     * @param string $advertiserId
     * @return array
     * @throws Exception
     */
    public function reportCustomConfig(string $advertiserId)
    {
        return $this->requestByToken('/open_api/v3.0/report/custom/config/get/', 'GET', [
            'advertiser_id' => $advertiserId,
            'data_topics' => ['BASIC_DATA'],
        ]);
    }

    public function getFund(string $advertiserId)
    {
        return $this->requestByToken('/open_api/2/advertiser/fund/get/', 'GET', [
            'advertiser_id' => $advertiserId,
        ]);
    }

    /**
     * 报表数据字短
     * @param bool $isNew
     * @return array
     */
    private function reportFields($isNew = false)
    {
        return $isNew ? [
            'cost' => 'stat_cost',// 展现数据-总花费
            'show' => 'show_cnt',// 展现数据-展示数
            'avg_show_cost' => 'cpm_platform',// 展现数据-平均千次展现费用
            'click' => 'click_cnt',// 展现数据-点击数
            'avg_click_cost' => 'cpc_platform',// 展现数据-平均点击单价
            'click_rate' => 'ctr',// 展现数据-点击率
            'convert' => 'convert_cnt',// 转化数据-转化数
            'convert_cost' => 'conversion_cost',// 转化数据-转化成本
            'convert_rate' => 'conversion_rate',// 转化数据-转化率
            //'deep_convert', // 转化数据-深度转化数
            //'deep_convert_cost', // 转化数据-深度转化成本
            //'deep_convert_rate', // 转化数据-深度转化率
            //'install_finish',// 应用下载广告数据-安卓安装完成数
            'active' => 'active',// 应用下载广告数据-激活数
            'active_cost' => 'active_cost', // 应用下载广告数据-激活成本
            'active_rate' => 'active_rate', // 应用下载广告数据-激活率
            'next_day_open' => 'attribution_next_day_open_cnt',// 应用下载广告数据-次留数
            'next_day_open_cost' => 'attribution_next_day_open_cost',// 应用下载广告数据-次留成本
            'next_day_open_rate' => 'attribution_next_day_open_rate',// 应用下载广告数据-次留率
            'key_action' => 'game_addiction',// 应用下载广告数据-关键行为数
            'key_action_cost' => 'game_addiction_cost', // 应用下载广告数据-关键行为成本
            'key_action_rate' => 'game_addiction_rate', // 应用下载广告数据-关键行为率
            'attribution_app_24h_roi' => 'attribution_app_24h_roi',
        ] : [
            'cost' => 'cost',// 展现数据-总花费
            'show' => 'show',// 展现数据-展示数
            'avg_show_cost' => 'avg_show_cost',// 展现数据-平均千次展现费用
            'click' => 'click',// 展现数据-点击数
            'avg_click_cost' => 'avg_click_cost',// 展现数据-平均点击单价
            'click_rate' => 'ctr',// 展现数据-点击率
            'convert' => 'convert',// 转化数据-转化数
            'convert_cost' => 'convert_cost',// 转化数据-转化成本
            'convert_rate' => 'convert_rate',// 转化数据-转化率
            //'deep_convert', // 转化数据-深度转化数
            //'deep_convert_cost', // 转化数据-深度转化成本
            //'deep_convert_rate', // 转化数据-深度转化率
            //'install_finish',// 应用下载广告数据-安卓安装完成数
            'active' => 'active',// 应用下载广告数据-激活数
            'active_cost' => 'active_cost', // 应用下载广告数据-激活成本
            'active_rate' => 'active_rate', // 应用下载广告数据-激活率
            'next_day_open' => 'attribution_next_day_open_cnt',// 应用下载广告数据-次留数
            'next_day_open_cost' => 'attribution_next_day_open_cost',// 应用下载广告数据-次留成本
            'next_day_open_rate' => 'attribution_next_day_open_rate',// 应用下载广告数据-次留率
            'key_action' => 'game_addiction',// 应用下载广告数据-关键行为数
            'key_action_cost' => 'game_addiction_cost', // 应用下载广告数据-关键行为成本
            'key_action_rate' => 'game_addiction_rate' // 应用下载广告数据-关键行为率
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
            'page' => $data['page'],
            'page_size' => $data['page_size'],
            'total_number' => $data['total_number'],
            'total_page' => $data['total_page']
        ];
    }

    public function log($line, ...$args)
    {
        $log_file = '/tmp/mkt_jl_log.txt';
        $time = date("Y-m-d H:i:s");
        $message = json_encode($args, JSON_UNESCAPED_UNICODE);
        $log_message = "$time $line $message \n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}
