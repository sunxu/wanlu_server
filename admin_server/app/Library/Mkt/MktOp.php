<?php

declare(strict_types=1);

namespace App\Library\Mkt;

use App\Utils\Log;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MktOp extends MktAbstract
{
    protected $baseUri = 'https://sapi.ads.heytapmobi.com/';// 正式

    protected $advertiserId;

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
            Log::http('error', '【MKT-OP】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        if (($content['code'] ?? -1) !== 0) {
            Log::http('info', '【MKT-OP】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
            throw new Exception($content['msg'] ?? '响应异常');
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
        $field = strtoupper($method) == 'POST' ? 'form_params' : 'query';
        $options = [
            'headers' => $headers,
            $field => $params
        ];

        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MKT-OP】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        Log::http('info', '【MKT-OP】', ['uri' => $uri, 'options' => $options, 'response' => $content]);

        if (($content['code'] ?? -1) !== 0) {
            Log::http('info', '【MKT-OP】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
            throw new Exception($content['msg'] ?? '响应异常');
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
     * @param null $advertiserId
     * @return string
     */
    public function getToken($advertiserId = null)
    {
        $advertiserId = $advertiserId ?? $this->advertiserId;
        $timeStamp = time();
        $appId = $this->appId;
        $appSecret = $this->appSecret;
        $sign = sha1($appId . $appSecret . $timeStamp);
        return 'Bearer ' . base64_encode($advertiserId . ',' . $appId . ',' . $timeStamp . ',' . $sign);
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
//        $this->advertiserId = $accountId;
//
//        $data = $this->accountRequestByToken('/v2/communal/agency/customer/list', 'POST', [
//            'page' => $page,
//            'pageCount' => $pageSize,
//        ]);
//
//        foreach (($data['items'] ?? []) as $v) {
//            $list[] = [
//                'account_id' => $accountId,
//                'advertiser_id' => $v['ownerId'],
//                'advertiser_name' => $v['ownerName'],
//            ];
//        }
//
//        if ($data['totalCount'] > $pageSize * $pageSize) {
//            return $this->advertiserGet($accountId, $page++, $pageSize, $list);
//        }
//
//        return $list;
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
        $this->advertiserId = $advertiserId;

        $pageSize = $pageSize < 1 ? 1 : ($pageSize > 100 ? 100 : $pageSize);
        $page = $page < 1 ? 1 : $page;

        $params = [
            'page' => $page,
            'limit' => $pageSize,
            'needStats' => 0//是否查询数据字段，0表示不查询，1表示查询（推荐使用0以提高性能）
        ];

        if (!empty($planIds)) {
            $params['planIds'] = implode(',', $planIds);
        }

        $data = $this->accountRequestByToken('/v3/plan/page', 'POST', $params);

        $putStatus = [
            0 => 1,
            1 => 2,
        ];

        $list = [];
        foreach ($data['records'] as $v) {
            $list[] = [
                'plan_id' => $v['planId'],
                'plan_name' => $v['planName'],
                'advertiser_id' => $advertiserId,
                'put_status' => $putStatus[$v['status2']] ?? 0,
//                'budget_mode' => $v['budget_mode'] ?? '',
                'budget_amount' => round(intval($v['dayBudget']) / 100, 4),
//                'schedule_start' => $v['begin_time'],
//                'schedule_end' => $v['end_time'],
//                'schedule_time' => $v['schedule_time'],
                'update_time' => date('Y-m-d H:i:s', $v['updateTime']),
                'create_time' => date('Y-m-d H:i:s', $v['insertTime']),
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
        $this->advertiserId = $advertiserId;

        $timeGranularity = strtoupper($granularity) == 'DAILY' ? 'DAY' : 'HOUR';

        $d = date('Ymd', strtotime($date));

        $data = $this->requestByToken('/v3/data/common/query/queryAdData', 'POST', [
            'beginTime' => $d,
            'endTime' => $d,
            'timeLevel' => $timeGranularity,// 时间粒度
            'paraMap' => [
                'group_by' => 'owner_id',
                'filter_zero' => 1,
            ],
            'page' => 1,
            'pageCount' => 500,
        ]);

        $list = [];
        foreach ($data['items'] ?? [] as $v) {
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
        $this->advertiserId = $advertiserId;

        $timeGranularity = strtoupper($granularity) == 'DAILY' ? 'DAY' : 'HOUR';

        $d = date('Ymd', strtotime($date));

        $params = [
            'beginTime' => $d,
            'endTime' => $d,
            'timeLevel' => $timeGranularity,// 时间粒度
            'paraMap' => [
                'group_by' => 'plan_id',
                'filter_zero' => 1,
            ],
            'page' => $page,
            'pageCount' => $pageSize,
        ];
        if (!empty($planIds)) {
            $params['paraMap']['planIds'] = $planIds;
        }

        $data = $this->requestByToken('/v3/data/common/query/queryAdData', 'POST', $params);

        $list = [];
        foreach ($data['items'] ?? [] as $v) {
            $d = $this->reportFieldsFormat($v);

            $d['date'] = $date;
            $d['advertiser_id'] = $advertiserId;
            $d['plan_id'] = $v['plan_id'];

//            if ($timeGranularity == 'HOURLY') {
//                $d['hour'] = date('H', strtotime($v['stat_datetime'] . '00'));
//            }

            $list[] = $d;
        }

        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($page, $pageSize, $data['totalCount'])
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
            'show' => 'expose',// 展现数据-素材曝光
            'avg_show_cost' => 'ecpm',// 展现数据-平均千次展现费用
            'click' => 'click',// 展现数据-点击数
            'avg_click_cost' => 'click_price',// 展现数据-平均行为单价
            'click_rate' => 'click_rate',// 展现数据-行为率
            'convert' => 'convert_app_custom',// 转化数据-转化数
            'convert_cost' => 'convert_app_custom_price',// 转化数据-转化成本
//            'convert_rate' => 'click_conversion_ratio',// 转化数据-转化率
            //'deep_convert', // 转化数据-深度转化数
            //'deep_convert_cost', // 转化数据-深度转化成本
            //'deep_convert_rate', // 转化数据-深度转化率
            //'install_finish',// 应用下载广告数据-安卓安装完成数
            'active' => 'convert_active',// 应用下载广告数据-激活数
            'active_cost' => 'convert_active_price', // 应用下载广告数据-激活成本
//            'active_rate' => 'conversion_ratio', // 应用下载广告数据-激活率
            'next_day_open' => 'convert_retention',// 应用下载广告数据-次留数
            'next_day_open_cost' => 'convert_retention_price',// 应用下载广告数据-次留成本
            'next_day_open_rate' => 'convert_retention_rate',// 应用下载广告数据-次留率
            'key_action' => 'convert_app_custom',// 应用下载广告数据-关键行为数
            'key_action_cost' => 'convert_app_custom_price', // 应用下载广告数据-关键行为成本
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
        // 金额单位分转元
        foreach ($data as $k => $v) {
            if (isset($reportFields[$k])) {
                $format[$reportFields[$k]] = strstr($reportFields[$k], 'cost') ? $v / 100 : $v;
            }
        }
        // 千次曝光
        $format['avg_show_cost'] = ($format['cost'] ?? 0) > 0 && ($format['show'] ?? 0) > 0 ? round(($format['cost'] ?? 0) * 1000 / ($format['show'] ?? 0), 2) : 0;
        // 转化率百分比
        $format['convert_rate'] = ($format['convert'] ?? 0) > 0 && ($format['click'] ?? 0) > 0 ? round(($format['convert'] ?? 0) * 1000 / ($format['click'] ?? 0), 2) : 0;
        // 无关键行为时用快应用关键行为
        if (($format['quick_app_act_num'] ?? 0) > 0) {
            $fommat['key_action'] = $data['quick_app_act_num'] ?? 0;
            $fommat['key_action_cost'] = $data['quick_app_act_cost'] ?? 0;
        }

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

    public function authAccessToken(string $authCode)
    {
        // TODO: Implement authAccessToken() method.
    }

    public function authRefreshToken(string $refreshToken)
    {
        // TODO: Implement authRefreshToken() method.
    }
}