<?php

declare(strict_types=1);

namespace App\Library\Mkt;

use Exception;

/** 荣耀：广告活动对应项目，广告组对应广告，复用现有投放表。 */
class MktHonor extends MktAbstract
{
    protected $baseUri = 'https://ads.cloud.honor.com';
    protected $authBaseUri = 'https://iam.developer.honor.com';
    private const TOKEN_PATH = '/auth/realms/advertisement/protocol/openid-connect/token';

    public function request(string $uri = '', string $method = 'GET', array $params = [], array $headers = [])
    {
        $auth = $uri === self::TOKEN_PATH;
        $options = ['headers' => $headers, 'timeout' => 30, 'connect_timeout' => 10];
        if (strtoupper($method) === 'GET') {
            $options['query'] = $params;
        } elseif ($auth) {
            $options['form_params'] = $params;
        } else {
            $options['json'] = $params;
        }
        try {
            $response = $this->getClient($auth ? $this->authBaseUri : $this->baseUri)->request($method, $uri, $options);
            $content = json_decode((string)$response->getBody(), true, 512, JSON_BIGINT_AS_STRING);
        } catch (\Throwable $e) {
            // 不传播可能含有密钥或 Authorization 的底层异常。
            throw new Exception('荣耀接口请求失败：' . $uri);
        }
        if (!is_array($content)) {
            throw new Exception('荣耀接口响应格式错误：' . $uri);
        }
        if ($auth) {
            if (!empty($content['error'])) {
                throw new Exception('荣耀客户端凭证鉴权失败');
            }
            return $content;
        }
        if (!in_array($content['code'] ?? null, [0, '0'], true) || !array_key_exists('data', $content)) {
            throw new Exception('荣耀接口异常：' . $uri . '，错误码 ' . (string)($content['code'] ?? '缺失'));
        }
        if ($content['data'] === null && $uri === '/openapi/v2_1/promotion/ad-group/status/update') { return []; }
        if (!is_array($content['data'])) { throw new Exception('荣耀接口数据格式错误：' . $uri); }
        $data = $content['data'];
        if (isset($data['adPageResponse']['code']) && !in_array($data['adPageResponse']['code'], [0, '0'], true)) {
            throw new Exception('荣耀报表查询失败，错误码 ' . (string)$data['adPageResponse']['code']);
        }
        return $data;
    }

    public function requestByToken(string $uri, string $method = 'POST', array $params = [])
    {
        return $this->request($uri, $method, $params, [
            'Authorization' => $this->getToken(),
            'x-request-id' => create_uuid(),
        ]);
    }

    public function authAccessToken(string $authCode)
    {
        // 当前工程要求实现此抽象方法；原荣耀适配器只有凭证刷新，不提供网页授权。
        throw new Exception('荣耀沿用已有配置，通过 mkt:authToken 刷新令牌');
    }

    public function authRefreshToken(string $refreshToken)
    {
        $data = $this->request(self::TOKEN_PATH, 'POST', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->getAppId(),
            'client_secret' => $this->getAppSecret(),
        ]);
        if (!is_string($data['access_token'] ?? null) || $data['access_token'] === '' ||
            !is_string($data['token_type'] ?? null) || $data['token_type'] === '' ||
            !is_numeric($data['expires_in'] ?? null) || $data['expires_in'] <= 0) {
            throw new Exception('荣耀授权令牌响应不完整');
        }
        $token = [
            'app_id' => $this->getAppId(),
            'access_token' => $data['token_type'] . ' ' . $data['access_token'],
            'access_time' => time() + (int)$data['expires_in'],
        ];
        if (!empty($data['refresh_token'])) { $token['refresh_token'] = $data['refresh_token']; }
        return $token;
    }

    public function authUserInfo() { return []; }
    public function authAccountGet() { return []; }

    public function advertiserGet(string $accountId)
    {
        // 与参考工程一致：广告主ID取自已有账户配置，不查询或重建授权关系。
        return [['account_id' => $accountId, 'advertiser_id' => $accountId, 'advertiser_name' => $accountId]];
    }

    public function projectGet(string $advertiserId, int $page = 1, int $pageSize = 100, array $ids = [])
    {
        $params = $this->listParams($page, $pageSize, $ids);
        $data = $this->requestByToken('/openapi/v2_1/promotion/ad-campaign/query', 'POST', $params);
        $rows = $this->rows($data['adCampaignStatcVos'] ?? []);
        $list = [];
        foreach ($rows as $row) {
            $id = $this->id($row['adCampaignId'] ?? null);
            if ($ids && !in_array($id, array_map('strval', $ids), true)) { continue; }
            $list[] = [
                'advertiser_id' => $advertiserId, 'project_id' => $id,
                'project_name' => $row['adCampaignName'] ?? '',
                'put_status' => $this->status($row['adCampaignStatus'] ?? null),
                'create_time' => $this->dateTime($row['createDateTime'] ?? null),
                'update_time' => $this->dateTime($row['updateDateTime'] ?? null),
            ];
        }
        return ['list' => $list, 'page_info' => $this->pageInfo($data, $page, $pageSize, count($rows))];
    }

    public function adGet(string $advertiserId, int $page = 1, int $pageSize = 100, array $ids = [])
    {
        $data = $this->requestByToken('/openapi/v2_1/promotion/ad-group/query', 'POST', $this->listParams($page, $pageSize, $ids));
        $rows = $this->rows($data['data'] ?? []);
        $list = [];
        foreach ($rows as $row) {
            $id = $this->id($row['adGroupId'] ?? null);
            if ($ids && !in_array($id, array_map('strval', $ids), true)) { continue; }
            $item = [
                'advertiser_id' => $advertiserId, 'ad_id' => $id,
                'project_id' => $this->id($row['adCampaignId'] ?? null),
                'ad_name' => $row['groupName'] ?? '',
                'put_status' => $this->status($row['adGroupStatus'] ?? null),
            ];
            if (isset($row['adDayBudget'])) { $item['budget_amount'] = (float)$row['adDayBudget'] / 1000000; }
            $list[] = $item;
        }
        return ['list' => $list, 'page_info' => $this->pageInfo($data, $page, $pageSize, count($rows))];
    }

    public function reportAdvertiserGet(string $advertiserId, string $date, $granularity = 'daily', $isSearch = false)
    {
        return $this->report($advertiserId, $date, 'advertiser', $granularity);
    }

    public function reportProjectGet(string $advertiserId, string $date, array $ids = [], $granularity = 'daily', int $page = 1, int $pageSize = 100, $unused = null)
    {
        $list = $page === 1 ? $this->report($advertiserId, $date, 'ad-campaign', $granularity, $ids) : [];
        return $this->allPage($list, $page);
    }

    public function reportAdGet(string $advertiserId, string $date, array $ids = [], $granularity = 'daily', int $page = 1, int $pageSize = 100, $unused = null)
    {
        $list = $page === 1 ? $this->report($advertiserId, $date, 'ad-group', $granularity, $ids) : [];
        return $this->allPage($list, $page);
    }

    private function report(string $advertiserId, string $date, string $level, string $granularity, array $ids = []): array
    {
        $timestamp = strtotime($date);
        if ($timestamp === false || !in_array(strtolower($granularity), ['daily', 'hourly'], true)) {
            throw new Exception('荣耀报表日期或粒度无效');
        }
        $date = date('Y-m-d', $timestamp);
        $hourly = strtolower($granularity) === 'hourly';
        $data = $this->requestByToken('/openapi/v2_1/support/ad-report/' . $level, 'POST', [
            'startTime' => $date, 'endTime' => $date, 'timeDimension' => $hourly ? 1 : 0, 'isQryAll' => 1,
        ]);
        if (!isset($data['adPageResponse']) || !is_array($data['adPageResponse'])) {
            throw new Exception('荣耀报表缺少 adPageResponse');
        }
        $response = $data['adPageResponse'];
        $rows = $this->rows($response['data'] ?? []);
        // 全量模式不能把被截断的响应作为完整报表写入。
        if (isset($response['totalCount']) && (int)$response['totalCount'] > count($rows)) {
            throw new Exception('荣耀全量报表响应不完整');
        }
        $list = [];
        foreach ($rows as $row) {
            $item = $this->reportFields($row['metrics'] ?? []);
            $item['date'] = $date;
            $item['advertiser_id'] = $advertiserId;
            if ($hourly) { $item['hour'] = $this->hour($row['statcHour'] ?? $row['hour'] ?? null); }
            if ($level !== 'advertiser') {
                $item['project_id'] = $this->id($row['adCampaignId'] ?? $row['metrics']['campaignId'] ?? null);
                if ($level === 'ad-group') { $item['ad_id'] = $this->id($row['adGroupId'] ?? null); }
                if ($ids && !in_array($level === 'ad-group' ? $item['ad_id'] : $item['project_id'], array_map('strval', $ids), true)) { continue; }
            }
            $list[] = $item;
        }
        if ($level !== 'advertiser') { return $list; }
        // 公共日报取第一行，小时报表按小时覆盖；在适配器内合并同粒度行。
        $groups = [];
        foreach ($list as $item) {
            $key = $item['hour'] ?? 'daily';
            if (!isset($groups[$key])) { $groups[$key] = $item; continue; }
            foreach (['cost', 'show', 'click', 'active', 'next_day_open'] as $field) { $groups[$key][$field] += $item[$field]; }
            $groups[$key]['avg_click_cost'] = $groups[$key]['click'] > 0 ? $groups[$key]['cost'] / $groups[$key]['click'] : 0;
            $groups[$key]['click_rate'] = $groups[$key]['show'] > 0 ? $groups[$key]['click'] / $groups[$key]['show'] * 100 : 0;
            $groups[$key]['active_cost'] = $groups[$key]['active'] > 0 ? $groups[$key]['cost'] / $groups[$key]['active'] : 0;
            $groups[$key]['active_rate'] = $groups[$key]['click'] > 0 ? $groups[$key]['active'] / $groups[$key]['click'] * 100 : 0;
        }
        return array_values($groups);
    }

    private function reportFields(array $metrics): array
    {
        $fields = ['cost' => 'adBilling', 'show' => 'impression', 'click' => 'click',
            'avg_click_cost' => 'aveClick', 'click_rate' => 'clickRate', 'active' => 'activate',
            'active_cost' => 'activateCost', 'active_rate' => 'activateRate', 'next_day_open' => 'retain'];
        $result = [];
        foreach ($fields as $target => $source) {
            $result[$target] = (float)($metrics[$source] ?? 0);
            if (in_array($target, ['cost', 'avg_click_cost', 'active_cost'], true)) { $result[$target] /= 1000000; }
        }
        return $result;
    }

    public function adBatchUpdateStatus(string $advertiserId, array $data = [])
    {
        if (!$data || count($data) > 10) { throw new Exception('批量限制1～10条'); }
        $ids = [];
        $batchStatus = null;
        foreach ($data as $row) {
            $status = $row['status'] ?? '';
            if (!in_array($status, ['enable', 'disable'], true)) { throw new Exception('荣耀广告状态无效'); }
            $id = $this->id($row['ad_id'] ?? null);
            if (isset($ids[$id])) { throw new Exception('荣耀广告ID重复'); }
            if ($batchStatus !== null && $batchStatus !== $status) { throw new Exception('荣耀单次批量请求必须使用相同状态'); }
            $batchStatus = $status;
            $ids[$id] = $id;
        }
        $this->requestByToken('/openapi/v2_1/promotion/ad-group/status/update', 'POST', [
            'adGroupIdList' => array_values($ids), 'adGroupStatus' => $batchStatus === 'enable' ? 1 : 0,
        ]);
        return ['ad_ids' => array_values($ids)];
    }

    private function listParams(int $page, int $pageSize, array $ids): array
    {
        if ($page < 1 || $pageSize < 1 || $pageSize > 100) { throw new Exception('荣耀分页参数无效'); }
        $params = ['pageIndex' => $page, 'pageSize' => $pageSize];
        if ($ids) { $params['ids'] = array_map('strval', $ids); }
        return $params;
    }

    private function pageInfo(array $data, int $page, int $pageSize, int $count): array
    {
        if (!isset($data['totalCount']) || !is_numeric($data['totalCount']) || $data['totalCount'] < 0) {
            if ($count !== 0) { throw new Exception('荣耀列表缺少有效总条数'); }
            $data['totalCount'] = 0;
        }
        $total = (int)$data['totalCount'];
        if ($count === 0 && $total > ($page - 1) * $pageSize) { throw new Exception('荣耀列表分页响应不完整'); }
        return ['page' => $page, 'page_size' => $pageSize, 'total_number' => $total, 'total_page' => (int)ceil($total / $pageSize)];
    }

    private function allPage(array $list, int $page): array
    {
        return ['list' => $list, 'page_info' => ['page' => $page, 'page_size' => count($list), 'total_number' => count($list), 'total_page' => $list ? 1 : 0]];
    }

    private function rows($rows): array
    {
        if (!is_array($rows)) { throw new Exception('荣耀列表格式错误'); }
        foreach ($rows as $row) { if (!is_array($row)) { throw new Exception('荣耀数据行格式错误'); } }
        return $rows;
    }

    private function id($id): string
    {
        if ((!is_string($id) && !is_int($id)) || !preg_match('/^[0-9]{1,50}$/D', (string)$id) || trim((string)$id, '0') === '') {
            throw new Exception('荣耀响应缺少有效业务ID');
        }
        return (string)$id;
    }

    private function status($value): int
    {
        return ['1' => 1, '0' => 2][(string)$value] ?? 0;
    }

    private function dateTime($value)
    {
        if (!$value || ($time = strtotime((string)$value)) === false) { return null; }
        return date('Y-m-d H:i:s', $time);
    }

    private function hour($value): string
    {
        if (is_scalar($value) && preg_match('/^([01]?[0-9]|2[0-3])(?::00(?::00)?)?$/D', (string)$value, $match)) {
            return sprintf('%02d', (int)$match[1]);
        }
        throw new Exception('荣耀小时报表缺少有效小时');
    }

    public function planGet(string $advertiserId) { throw new Exception('荣耀使用项目／广告层级，不支持计划'); }
    public function reportPlanGet(string $advertiserId, string $date) { throw new Exception('荣耀不支持计划报表'); }
    public function planUpdate(string $advertiserId, string $planId, array $params = []) { throw new Exception('荣耀不支持计划修改'); }
    public function planUpdateStatus(string $advertiserId, string $planId, string $putStatus) { throw new Exception('荣耀不支持计划启停'); }
    public function planUpdateBudget(string $advertiserId, string $planId, float $budgetAmount) { throw new Exception('荣耀不支持预算修改'); }
}
