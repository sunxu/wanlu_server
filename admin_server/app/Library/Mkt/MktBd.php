<?php

declare(strict_types=1);

namespace App\Library\Mkt;

use App\Constants\MktConst;
use App\Utils\Log;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MktBd extends MktAbstract
{
    protected $baseUri = 'https://api.baidu.com';
    protected $authUri = 'https://u.baidu.com';

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
            Log::http('error', '【MKT-BD】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);
        Log::http('info', '【MKT-BD】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
        if (($content['header']['status'] ?? -1) !== 0) {
            Log::http('info', '【MKT-BD】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
            throw new Exception($content['header']['failures'][0]['message'] ?? '响应异常');
        }
        return (array)$content['body']['data'];
    }

    /**
     * 请求
     * @param string $uri
     * @param string $method
     * @param array $params
     * @param array $headers
     * @return array
     * @throws Exception
     */
    public function requestAuth(string $uri = '', string $method = 'GET', array $params = [], array $headers = []): array
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
            $response = $this->getClient($this->authUri)->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MKT-BD】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        if (($content['code'] ?? -1) !== 0) {
            Log::http('info', '【MKT-BD】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
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
        $params['header']['accessToken'] = $this->getToken();
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
        list($code, $userId) = $this->explodeInfo($authCode);

        $data = $this->requestAuth('/oauth/accessToken', 'POST', [
            'appId' => $this->getAppId(),
            'secretKey' => $this->getAppSecret(),
            'grantType' => 'auth_code',
            'authCode' => $code,
            'userId' => $userId
        ]);

        if (empty($data['accessToken'])) {
            throw new Exception(__CLASS__ . ':' . __FUNCTION__ . '响应异常');
        }

        return [
            'app_id' => $this->getAppId(),
            'access_token' => $data['accessToken'],
            'access_time' => time() + $data['expiresIn'],
            'refresh_token' => $data['refreshToken'],
            'refresh_time' => time() + $data['refreshExpiresIn'],
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
        list($code, $userId) = $this->explodeInfo($refreshToken);

        $data = $this->requestAuth('/oauth/refreshToken', 'POST', [
            'appId' => $this->getAppId(),
            'secretKey' => $this->getAppSecret(),
            'refreshToken' => $code,
            'userId' => $userId
        ]);

        if (empty($data['accessToken'])) {
            throw new Exception(__CLASS__ . ':' . __FUNCTION__ . '响应异常');
        }

        return [
            'app_id' => $this->getAppId(),
            'access_token' => $data['accessToken'],
            'access_time' => time() + $data['expiresIn'],
            'refresh_token' => $data['refreshToken'],
            'refresh_time' => time() + $data['refreshExpiresIn'],
        ];
    }

    /**
     * 获取帐户下广告主列表
     * @param string $accountId
     * @return array
     * @throws Exception
     */
    public function advertiserGet(string $accountId)
    {
        list($accountId, $accountName) = $this->explodeInfo($accountId);
        $params = [
            'header' => [
                'userName' => $accountName ?? ''
            ],
            'body' => [
                'mccid' => null
            ]
        ];
        $data = $this->requestByToken('/json/feed/v1/MccFeedService/getUserListByMccid', 'POST', $params);

        $list = [];
        foreach (($data ?? []) as $v) {
            $list[] = [
                'account_id' => $accountId,
                'advertiser_id' => $v['userid'],
                'advertiser_name' => $v['username'],
            ];
        }

        return $list;
    }

    /**
     * 广告主数据
     * @param string $advertiserId
     * @param string $date
     * @param string $granularity
     * @param bool $isSearch
     * @return array
     * @throws Exception
     */
    public function reportAdvertiserGet(string $advertiserId, string $date, $granularity = 'daily', $isSearch = false)
    {
        list($advertiserId, $advertiserName) = $this->explodeInfo($advertiserId ?? '');

        $timeGranularity = strtoupper($granularity) == 'DAILY' ? 'DAY' : 'HOUR';

        $date = date('Y-m-d', strtotime($date));

        $columns = array_values($this->reportFields($isSearch));
        array_push($columns, 'wBudget');

        $params = [
            'header' => [
                'userName' => $advertiserName ?? ''
            ],
            'body' => [
                'reportType' => $isSearch ? 2208157 : 2149145,
                'startDate' => $date,
                'endDate' => $date,
                'columns' => $columns,
                'timeUnit' => $timeGranularity,// 时间粒度
                'filters' => [
                    [
                        'column' => 'cost',
                        'operator' => 'GT',
                        'values' => ['0'],
                    ],
                ],
                'startRow' => 0,
                'rowCount' => 1000,
            ]
        ];
        $data = $this->requestByToken('/json/sms/service/OpenApiReportService/getReportData', 'POST', $params);

        $list = [];
        foreach ($data[0]['rows'] ?? [] as $v) {
            $d = $this->reportFieldsFormat($v, $isSearch);
            $d['hour'] = date('H', strtotime($v['date']));
            $d['date'] = date('Y-m-d', strtotime($v['date']));
            $d['advertiser_id'] = $advertiserId;
            $d['budget'] = $v['wBudget'] ?? 0;
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
     * @param bool $isSearch
     * @return array
     * @throws Exception
     */
    public function planGet(string $advertiserId, int $page = 1, int $pageSize = 100, array $ids = [], $isSearch = false)
    {
        if ($isSearch) {
            return $this->splanGet($advertiserId, $page, $pageSize, $ids);
        }
        $pageSize = $pageSize < 1 ? 1 : ($pageSize > 100 ? 100 : $pageSize);
        $page = $page < 1 ? 1 : $page;

        list($advertiserId, $advertiserName) = $this->explodeInfo($advertiserId);

        $params = [
            'header' => [
                'userName' => $advertiserName ?? ''
            ],
            'body' => [
                'campaignFeedFields' => [
                    'campaignFeedId',
                    'campaignFeedName',
                    'starttime',
                    'endtime',
                    'addtime',
                    'budget',
                    'pause',
                ]
            ]
        ];

        if (!empty($ids)) {
            $params['body']['campaignFeedIds'] = $ids;
        }

        $data = $this->requestByToken('/json/feed/v1/CampaignFeedService/getCampaignFeed', 'POST', $params);

        $list = [];
        foreach ($data as $v) {
            $list[] = [
                'plan_id' => $v['campaignFeedId'],
                'plan_name' => $v['campaignFeedName'],
                'advertiser_id' => $advertiserId,
                'put_status' => $v['pause'] ? 2 : 1,
                'budget_amount' => $v['budget'] ?? 0,
                'schedule_start' => date('Y-m-d H:i:s', strtotime($v['starttime'])),
                'schedule_end' => date('Y-m-d H:i:s', strtotime($v['endtime'])),
                'create_time' => date('Y-m-d H:i:s', strtotime($v['addtime'])),
            ];
        }

        $total = count($list);
        return [
            'list' => $list,
            'page_info' => [
                'page' => 1,
                'page_size' => $total,
                'total_number' => $total,
                'total_page' => 1
            ]
        ];
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
    public function splanGet(string $advertiserId, int $page = 1, int $pageSize = 100, array $ids = [])
    {

        $pageSize = $pageSize < 1 ? 1 : ($pageSize > 100 ? 100 : $pageSize);
        $page = $page < 1 ? 1 : $page;

        list($advertiserId, $advertiserName) = $this->explodeInfo($advertiserId);

        $params = [
            'header' => [
                'userName' => $advertiserName ?? ''
            ],
            'body' => [
                'campaignFields' => [
                    'campaignId',
                    'campaignName',
                    'budget',
                    'pause',
                ]
            ]
        ];

        if (!empty($ids)) {
            $params['body']['campaignIds'] = $ids;
        }

        $data = $this->requestByToken('/json/sms/service/CampaignService/getCampaign', 'POST', $params);

        $list = [];
        foreach ($data as $v) {
            $list[] = [
                'plan_id' => $v['campaignId'],
                'plan_name' => $v['campaignName'],
                'advertiser_id' => $advertiserId,
                'put_status' => $v['pause'] ? 2 : 1,
                'budget_amount' => $v['budget'] ?? 0,
            ];
        }

        $total = count($list);
        return [
            'list' => $list,
            'page_info' => [
                'page' => 1,
                'page_size' => $total,
                'total_number' => $total,
                'total_page' => 1
            ]
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
     * @param bool $isSearch
     * @return array
     * @throws Exception
     */
    public function reportPlanGet(string $advertiserId, string $date, array $planIds = [], $granularity = 'daily', int $page = 1, int $pageSize = 100, $isSearch = false)
    {
        list($advertiserId, $advertiserName) = $this->explodeInfo($advertiserId ?? '');

        $timeGranularity = strtoupper($granularity) == 'DAILY' ? 'DAY' : 'HOUR';

        $date = date('Y-m-d', strtotime($date));

        $columns = array_values($this->reportFields($isSearch));
        array_push($columns, 'campaignId');


        $params = [
            'header' => [
                'userName' => $advertiserName ?? ''
            ],
            'body' => [
                'reportType' => $isSearch ? 2290316 : 2276038,
                'startDate' => $date,
                'endDate' => $date,
                'columns' => $columns,
                'timeUnit' => $timeGranularity,// 时间粒度
                'filters' => [
                    [
                        'column' => 'cost',
                        'operator' => 'GT',
                        'values' => ['0'],
                    ],
                ],
                'startRow' => 0,
                'rowCount' => 1000,
            ]
        ];

        $data = $this->requestByToken('/json/sms/service/OpenApiReportService/getReportData', 'POST', $params);

        $list = [];
        foreach ($data[0]['rows'] ?? [] as $v) {
            $d = $this->reportFieldsFormat($v, $isSearch);
            $d['hour'] = date('H', strtotime($v['date']));
            $d['date'] = date('Y-m-d', strtotime($v['date']));
            $d['advertiser_id'] = $advertiserId;
            $d['plan_id'] = $v['campaignId'];
            $list[] = $d;
        }

        return [
            'list' => $list,
            'page_info' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total_number' => $data[0]['totalRowCount'] ?? 0,
                'total_page' => ceil(($data[0]['totalRowCount'] ?? 0) / $pageSize),
            ]
        ];
    }

    /**
     *计划更新状态
     * @param string $advertiserId
     * @param string $planId
     * @param string $putStatus
     * @param bool $isSearch
     * @return bool
     * @throws Exception
     */
    public function planUpdateStatus(string $advertiserId, string $planId, string $putStatus = 'enable', $isSearch = false)
    {
        list($advertiserId, $advertiserName) = $this->explodeInfo($advertiserId ?? '');

        $pause = $putStatus == 'enable' ? false : true;

        $params = [
            'header' => [
                'userName' => $advertiserName ?? ''
            ],
            'body' => $isSearch ? [
                'campaignTypes' => [
                    [
                        'campaignId' => $planId,
                        'pause' => $pause
                    ]
                ]
            ] : [
                'campaignFeedTypes' => [
                    [
                        'campaignFeedId' => $planId,
                        'pause' => $pause
                    ]
                ]
            ]
        ];

        $data = $this->requestByToken($isSearch ? '/json/sms/service/CampaignService/updateCampaign' : '/json/feed/v1/CampaignFeedService/updateCampaignFeed', 'POST', $params);

        return isset($data[0]['pause']) && $data[0]['pause'] == $pause;
    }


    /**
     * 计划更新预算
     * @param string $advertiserId
     * @param string $planId
     * @param float $budgetAmount
     * @param bool $isSearch
     * @return bool
     * @throws Exception
     */
    public function planUpdateBudget(string $advertiserId, string $planId, float $budgetAmount, $isSearch = false)
    {
        list($advertiserId, $advertiserName) = $this->explodeInfo($advertiserId ?? '');

        $params = [
            'header' => [
                'userName' => $advertiserName ?? ''
            ],
            'body' => $isSearch ? [
                'campaignTypes' => [
                    [
                        'campaignId' => $planId,
                        'budget' => $budgetAmount
                    ]
                ]
            ] : [
                'campaignFeedTypes' => [
                    [
                        'campaignFeedId' => $planId,
                        'budget' => $budgetAmount
                    ]
                ]
            ]
        ];
        $data = $this->requestByToken($isSearch ? '/json/sms/service/CampaignService/updateCampaign' : '/json/feed/v1/CampaignFeedService/updateCampaignFeed', 'POST', $params);

        return isset($data[0]['budget']) && $data[0]['budget'] == $budgetAmount;
    }

    /**
     * 广告列表-单元
     * @param string $advertiserId
     * @param int $page
     * @param int $pageSize
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function adGet(string $advertiserId, int $page = 1, int $pageSize = 100, array $ids = [], $isSearch = false)
    {
        if ($isSearch) {
            return $this->splanGet($advertiserId, $page, $pageSize, $ids);
        }
        $pageSize = $pageSize < 1 ? 1 : ($pageSize > 100 ? 100 : $pageSize);
        $page = $page < 1 ? 1 : $page;

        list($advertiserId, $advertiserName) = $this->explodeInfo($advertiserId);

        $params = [
            'header' => [
                'userName' => $advertiserName ?? ''
            ],
            'body' => [
                'campaignFeedFields' => [
                    'campaignFeedId',
                    'campaignFeedName',
                    'starttime',
                    'endtime',
                    'addtime',
                    'budget',
                    'pause',
                ]
            ]
        ];

        if (!empty($ids)) {
            $params['body']['campaignFeedIds'] = $ids;
        }

        $data = $this->requestByToken('/json/feed/v1/AdgroupFeedService/getAdgroupFeed', 'POST', $params);

        $list = [];
        foreach ($data as $v) {
            $list[] = [
                'ad_id' => $v['adgroupFeedId'],
                'ad_name' => $v['adgroupFeedName'] ?? '',
                'project_id' => $v['campaignFeedId'],
                'advertiser_id' => $advertiserId,
                'put_status' => $v['pause'] ? 2 : 1,
                'budget_amount' => $v['budget'] ?? 0,
                'update_time' => $v['promotion_modify_time'],
                'create_time' => $v['promotion_create_time'],

                'plan_id' => $v['campaignFeedId'],
                'plan_name' => $v['campaignFeedName'],
                'advertiser_id' => $advertiserId,
                'put_status' => $v['pause'] ? 2 : 1,
                'budget_amount' => $v['budget'] ?? 0,
                'schedule_start' => date('Y-m-d H:i:s', strtotime($v['starttime'])),
                'schedule_end' => date('Y-m-d H:i:s', strtotime($v['endtime'])),
                'create_time' => date('Y-m-d H:i:s', strtotime($v['addtime'])),
            ];
        }

        $total = count($list);
        return [
            'list' => $list,
            'page_info' => [
                'page' => 1,
                'page_size' => $total,
                'total_number' => $total,
                'total_page' => 1
            ]
        ];
    }

    /**
     * 报表数据字短
     * @return array
     */
    private function reportFields($isSearch = false)
    {
        return $isSearch ? [
            'cost' => 'cost',// 展现数据-总花费
            'show' => 'impression',// 展现数据-展示数
            'avg_show_cost' => 'cpm',// 展现数据-平均千次展现费用
            'click' => 'click',// 展现数据-点击数
            'avg_click_cost' => 'cpc',// 展现数据-平均点击单价
            'click_rate' => 'ctr',// 展现数据-点击率
//            'convert' => 'convert_cnt',// 转化数据-转化数
//            'convert_cost' => 'conversion_cost',// 转化数据-转化成本
//            'convert_rate' => 'conversion_rate',// 转化数据-转化率
            'active' => 'ocpcConversionsDetail4',// 应用下载广告数据-激活数
            'active_cost' => 'ocpcConversionsDetail4Cost', // 应用下载广告数据-激活成本
            'active_rate' => 'ocpcConversionsDetail4CVR', // 应用下载广告数据-激活率
            'next_day_open' => 'ocpcConversionsDetail28',// 应用下载广告数据-次留数
            'next_day_open_cost' => 'ocpcConversionsDetail28Cost',// 应用下载广告数据-次留成本
            'next_day_open_rate' => 'ocpcConversionsDetail28CVR',// 应用下载广告数据-次留率
            'key_action' => 'ocpcAppConversionsDetail99',// 应用下载广告数据-关键行为数
            'key_action_cost' => 'ocpcAppConversionsDetail99Cost', // 应用下载广告数据-关键行为成本
            'key_action_rate' => 'ocpcAppConversionsDetail99CVR' // 应用下载广告数据-关键行为率
        ] : [
            'cost' => 'cost',// 展现数据-总花费
            'show' => 'impression',// 展现数据-展示数
            'avg_show_cost' => 'cpm',// 展现数据-平均千次展现费用
            'click' => 'click',// 展现数据-点击数
            'avg_click_cost' => 'cpc',// 展现数据-平均点击单价
            'click_rate' => 'ctr',// 展现数据-点击率
//            'convert' => 'convert_cnt',// 转化数据-转化数
//            'convert_cost' => 'conversion_cost',// 转化数据-转化成本
//            'convert_rate' => 'conversion_rate',// 转化数据-转化率
            'active' => 'activeConversions',// 应用下载广告数据-激活数
            'active_cost' => 'activeConversionsCost', // 应用下载广告数据-激活成本
            'active_rate' => 'activeConversionsCVR', // 应用下载广告数据-激活率
            'next_day_open' => 'retentions',// 应用下载广告数据-次留数
            'next_day_open_cost' => 'retentionsCost',// 应用下载广告数据-次留成本
            'next_day_open_rate' => 'activeRetentionRate',// 应用下载广告数据-次留率
            'key_action' => 'keyActionUv',// 应用下载广告数据-关键行为数
            'key_action_cost' => 'keyActionUvCost', // 应用下载广告数据-关键行为成本
            'key_action_rate' => 'keyActionUvCVR' // 应用下载广告数据-关键行为率
        ];
    }

    /**
     * 统一报表字段
     * @param array $data
     * @param bool $isSearch
     * @return array
     */
    private function reportFieldsFormat(array $data = [], $isSearch = false)
    {
        if (empty($data)) {
            return [];
        }

        $reportFields = array_flip($this->reportFields($isSearch));
        $format = [];

        foreach ($data as $k => $v) {
            if (isset($reportFields[$k])) {
                $format[$reportFields[$k]] = $v;
            }
        }

        if (isset($format['key_action_rate'])) {
            $format['key_action_rate'] = $format['key_action_rate'] * 100;
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

    private function explodeInfo(string $str)
    {
        return explode(MktConst::PLATFORM_BD_DELIMITER, $str);
    }

    public function reportProjectGet(string $advertiserId, string $date)
    {
        // TODO: Implement reportProjectGet() method.
    }

    public function reportAdGet(string $advertiserId, string $date)
    {
        // TODO: Implement reportAdGet() method.
    }

    public function authUserInfo()
    {
        // TODO: Implement authUserInfo() method.
    }

    public function authAccountGet()
    {
        // TODO: Implement authAccountGet() method.
    }

    public function planUpdate(string $advertiserId, string $planId, array $params = [])
    {
        // TODO: Implement planUpdate() method.
    }
}
