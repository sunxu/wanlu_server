<?php

declare(strict_types=1);

namespace App\Library\Mkt;

use App\Utils\Log;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class MktKs extends MktAbstract
{
    protected $baseUri = 'https://ad.e.kuaishou.com';

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
        $options = [
            'headers' => $headers,
            'json' => $params
        ];

        try {
            $response = $this->getClient()->request($method, $uri, $options);
        } catch (GuzzleException $e) {
            Log::http('error', '【MKT-KS】', ['uri' => $uri, 'options' => $options, 'message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }

        $content = (array)json_decode($response->getBody()->getContents(), true);

        if (($content['code'] ?? -1) !== 0) {
            Log::http('info', '【MKT-KS】', ['uri' => $uri, 'options' => $options, 'response' => $content]);
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
        return $this->request($api, $method, $params, array_merge($headers, ['Access-Token' => $this->getToken()]));
    }

    /**
     * @param string $authCode
     * @return array
     * @throws Exception
     */
    public function authAccessToken(string $authCode)
    {
        $data = $this->request('/rest/openapi/oauth2/authorize/access_token', 'POST', [
            'app_id' => $this->getAppId(),
            'secret' => $this->getAppSecret(),
            'auth_code' => $authCode
        ]);

        if (empty($data['access_token'])) {
            throw new Exception(__CLASS__ . ':' . __FUNCTION__ . '响应异常');
        }

        return [
            'app_id' => $this->getAppId(),
            'access_token' => $data['access_token'],
            'access_time' => time() + $data['access_token_expires_in'],
            'refresh_token' => $data['refresh_token'],
            'refresh_time' => time() + $data['refresh_token_expires_in'],
        ];
    }

    /**
     * @param string $refreshToken
     * @return array
     * @throws Exception
     */
    public function authRefreshToken(string $refreshToken)
    {
        $data = $this->request('/rest/openapi/oauth2/authorize/refresh_token', 'POST', [
            'app_id' => $this->getAppId(),
            'secret' => $this->getAppSecret(),
            'refresh_token' => $refreshToken
        ]);

        if (empty($data['access_token'])) {
            throw new Exception(__CLASS__ . ':' . __FUNCTION__ . '响应异常');
        }

        return [
            'app_id' => $this->getAppId(),
            'access_token' => $data['access_token'],
            'access_time' => time() + $data['access_token_expires_in'],
            'refresh_token' => $data['refresh_token'],
            'refresh_time' => time() + $data['refresh_token_expires_in'],
        ];
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
     * @return array
     * @throws Exception
     */
    public function advertiserGet(string $accountId)
    {
        $data = $this->requestByToken('/rest/openapi/gw/uc/v1/advertisers', 'POST', [
            'advertiser_id' => $accountId
        ]);

        $list = [];
        foreach (($data['details'] ?? []) as $v) {
            $list[] = [
                'account_id' => $accountId,
                'advertiser_id' => $v['advertiser_id'],
                'advertiser_name' => $v['advertiser_name'],
            ];
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
        $list = [];
        foreach ($advertiserIds as $advertiserId) {
            $data = $this->requestByToken('/rest/openapi/v1/advertiser/budget/get', 'GET', [
                'advertiser_id' => $advertiserId
            ]);
            $list[] = [
                'advertiser_id' => $advertiserId,
                'budget' => round($data['day_budget'] / 1000, 4),
                'date' => date('Y-m-d')
            ];
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
            'page' => $page,
            'page_size' => $pageSize,
        ];

        if (!empty($planIds)) {
            $params['unit_ids'] = $planIds;
        }

        $data = $this->requestByToken('/rest/openapi/gw/dsp/unit/list', 'GET', $params);

        $list = [];
        foreach ($data['details'] as $v) {
            $list[] = [
                'plan_id' => $v['unit_id'],
                'plan_name' => $v['unit_name'],
                'advertiser_id' => $advertiserId,
                'put_status' => $v['put_status'],
                'budget_mode' => $v['budget_mode'] ?? '',
                'budget_amount' => round($v['day_budget'] / 1000, 4),
                'schedule_start' => $v['begin_time'],
                'schedule_end' => $v['end_time'],
                'schedule_time' => $v['schedule_time'],
                'update_time' => $v['update_time'],
                'create_time' => $v['create_time'],
            ];
        }

        return [
            'list' => $list,
            'page_info' => $this->pageInfoFormat($page, $pageSize, $data['total_count'])
        ];
    }

    /**
     * @param string $advertiserId
     * @param string $planId
     * @param string $putStatus
     * @return bool
     * @throws Exception
     */
    public function planUpdateStatus(string $advertiserId, string $planId, string $putStatus = 'enable')
    {
        $status = [
            'enable' => 1,
            'disable' => 2,
            'delete' => 3,
        ];
        $data = $this->requestByToken('/rest/openapi/v1/ad_unit/update/status', 'POST', [
            'advertiser_id' => $advertiserId,
            'unit_ids' => [$planId],
            'put_status' => $status[$putStatus],
        ]);

        return in_array($planId, $data['unit_ids']);
    }

    /**
     * @param string $advertiserId
     * @param string $planId
     * @param float $budgetAmount
     * @return bool
     * @throws Exception
     */
    public function planUpdateBudget(string $advertiserId, string $planId, float $budgetAmount)
    {
        $data = $this->requestByToken('/rest/openapi/v1/ad_unit/update/day_budget', 'POST', [
            'advertiser_id' => $advertiserId,
            'unit_id' => $planId,
            'day_budget' => $budgetAmount * 1000,// 单位厘
        ]);

        return in_array($planId, $data['unit_ids']);
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

        $data = $this->requestByToken('/rest/openapi/v1/report/account_report', 'GET', [
            'advertiser_id' => $advertiserId,
            'start_date' => $date,
            'end_date' => $date,
            'temporal_granularity' => $timeGranularity,// 时间粒度
            'page' => 1,
            'page_size' => 1000,
        ]);

        $list = [];
        foreach ($data['details'] as $v) {
            $d = $this->reportFieldsFormat($v);

            $d['date'] = $date;
            $d['advertiser_id'] = $advertiserId;

            if ($timeGranularity == 'HOURLY') {
                $d['hour'] = date('H', strtotime($date . ' ' . $v['stat_hour'] . ':00:00'));
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
            'temporal_granularity' => $timeGranularity,// 时间粒度
            'page' => $page,
            'page_size' => $pageSize,
        ];

        if (!empty($planIds)) {
            $params['unit_ids'] = $planIds;
        }

        $data = $this->requestByToken('/rest/openapi/v1/report/unit_report', 'GET', $params);

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
     * 计划-更新信息
     * @param string $advertiserId
     * @return bool
     * @throws Exception
     */
    public function planUpdate(string $advertiserId, string $planId, array $params = [])
    {
        $data = $this->requestByToken('/rest/openapi/gw/dsp/unit/update', 'POST', array_merge([
            'advertiser_id' => $advertiserId,
            'unit_id' => $planId,
        ], $params));

        return $planId == $data['unit_id'];
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
            'cost' => 'charge',// 展现数据-总花费
            'show' => 'aclick',// 展现数据-素材曝光
//            'avg_show_cost' => cost * 1000 / show,// 展现数据-平均千次展现费用
            'click' => 'bclick',// 展现数据-点击数
            'avg_click_cost' => 'action_cost',// 展现数据-平均行为单价
            'click_rate' => 'action_ratio',// 展现数据-行为率
            'convert' => 'conversion_num',// 转化数据-转化数
            'convert_cost' => 'conversion_num_cost',// 转化数据-转化成本
            'convert_rate' => 'click_conversion_ratio',// 转化数据-转化率
            //'deep_convert', // 转化数据-深度转化数
            //'deep_convert_cost', // 转化数据-深度转化成本
            //'deep_convert_rate', // 转化数据-深度转化率
            //'install_finish',// 应用下载广告数据-安卓安装完成数
            'active' => 'activation',// 应用下载广告数据-激活数
            'active_cost' => 'conversion_cost', // 应用下载广告数据-激活成本
            'active_rate' => 'conversion_ratio', // 应用下载广告数据-激活率
            'next_day_open' => 'event_next_day_stay',// 应用下载广告数据-次留数
            'next_day_open_cost' => 'event_next_day_stay_cost',// 应用下载广告数据-次留成本
            'next_day_open_rate' => 'event_next_day_stay_ratio',// 应用下载广告数据-次留率
            'key_action' => 'key_action',// 应用下载广告数据-关键行为数
            'key_action_cost' => 'key_action_cost', // 应用下载广告数据-关键行为成本
            'key_action_rate' => 'key_action_ratio' // 应用下载广告数据-关键行为率
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

        foreach ($data as $k => $v) {
            if (isset($reportFields[$k])) {
                // 百分比转化
                $format[$reportFields[$k]] = strstr($reportFields[$k], 'rate') ? $v * 100 : $v;
            }
        }
        // 千次曝光
        $format['avg_show_cost'] = ($format['cost'] ?? 0) > 0 && ($format['show'] ?? 0) > 0 ? round(($format['cost'] ?? 0) * 1000 / ($format['show'] ?? 0), 2) : 0;
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
}