<?php

declare(strict_types=1);

namespace App\Library\Mkt;

use Exception;

/** 腾讯营销 v3.0：首期仅提供查询，投放设置禁止写入。 */
class MktTx extends MktAbstract
{
    protected $baseUri = 'https://api.e.qq.com';

    public function request(string $uri = '', string $method = 'GET', array $params = [], array $headers = [])
    {
        $query = [];
        $options = ['headers' => $headers, 'timeout' => 30, 'connect_timeout' => 10];
        if (strtoupper($method) === 'GET') {
            foreach ($params as $key => $value) {
                $query[$key] = is_array($value) || is_bool($value) ? json_encode($value) : $value;
            }
            $options['query'] = $query;
        } else {
            $options['json'] = $params;
        }
        try {
            $response = $this->getClient()->request($method, $uri, $options);
            $content = json_decode((string)$response->getBody(), true, 512, JSON_BIGINT_AS_STRING);
        } catch (\Throwable $e) {
            // 请求 URL 可能携带令牌或应用密钥，不向日志和调用方传播原始 URL。
            throw new Exception('腾讯营销请求失败：' . $uri);
        }
        if (!is_array($content) || !in_array($content['code'] ?? null, [0, '0'], true) || !isset($content['data'])) {
            throw new Exception('腾讯营销接口异常：' . ($content['message_cn'] ?? $content['message'] ?? '响应格式错误'));
        }
        return (array)$content['data'];
    }

    public function requestByToken(string $uri, array $params = [])
    {
        return $this->request('/v3.0/' . ltrim($uri, '/'), 'GET', array_merge($params, [
            'access_token' => $this->getToken(),
            'timestamp' => time(),
            'nonce' => bin2hex(random_bytes(16)),
        ]));
    }

    public function authAccessToken(string $authCode)
    {
        $data = $this->request('/oauth/token', 'GET', [
            'client_id' => $this->getAppId(),
            'client_secret' => $this->getAppSecret(),
            'grant_type' => 'authorization_code',
            'authorization_code' => $authCode,
            'redirect_uri' => $this->redirectUri(),
        ]);
        $info = $data['authorizer_info'] ?? [];
        $accountId = (string)($info['account_id'] ?? '');
        if (!ctype_digit($accountId) || (int)$accountId <= 0 || empty($data['refresh_token'])) {
            throw new Exception('腾讯授权未返回有效账户或刷新令牌');
        }
        if (preg_match('/AGENCY|T1|BUSINESS|ORGANIZATION/', $info['account_role_type'] ?? '')) {
            throw new Exception('本期仅支持腾讯广告主直客账号授权');
        }
        return array_merge($this->formatToken($data), [
            'user_id' => 'tx_' . $this->getAppId() . '_' . $accountId,
            'account_id' => $accountId,
            'account_role' => $info['account_role_type'] ?? '',
        ]);
    }

    public function authRefreshToken(string $refreshToken)
    {
        return $this->formatToken($this->request('/oauth/token', 'GET', [
            'client_id' => $this->getAppId(),
            'client_secret' => $this->getAppSecret(),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]));
    }

    protected function formatToken(array $data): array
    {
        if (empty($data['access_token']) || empty($data['access_token_expires_in'])) {
            throw new Exception('腾讯授权令牌响应不完整');
        }
        $token = [
            'app_id' => $this->getAppId(),
            'access_token' => $data['access_token'],
            'access_time' => time() + (int)$data['access_token_expires_in'],
        ];
        // 刷新接口通常不返回 refresh_token，不能将已有值清空或延长其期限。
        if (!empty($data['refresh_token'])) {
            $token['refresh_token'] = $data['refresh_token'];
        }
        if (!empty($data['refresh_token_expires_in'])) {
            $token['refresh_time'] = time() + (int)$data['refresh_token_expires_in'];
        }
        return $token;
    }

    public function redirectUri(): string
    {
        parse_str(parse_url($this->getAppInfo()['auth_url'] ?? '', PHP_URL_QUERY) ?? '', $query);
        $uri = $query['redirect_uri'] ?? '';
        if (!filter_var($uri, FILTER_VALIDATE_URL) || !in_array(parse_url($uri, PHP_URL_SCHEME), ['http', 'https'], true) || parse_url($uri, PHP_URL_PORT)) {
            throw new Exception('请在腾讯 mkt_app.auth_url 中配置有效的 redirect_uri');
        }
        return $uri;
    }

    public function authUserInfo()
    {
        return [];
    }

    public function authAccountGet()
    {
        // 直客授权关系由 token 响应的 authorizer_info 建立，无需组织账号接口。
        return [];
    }

    /**
     * Read-only, minimal advertiser identity query for private token validation.
     * Exposes only fixed diagnostics and bounded numeric HTTP/API codes; never logs
     * or returns the request URL, tokens or Tencent's free-form message.
     */
    public function advertiserProbe(string $accountId): array
    {
        $options = [
            'query' => [
                'account_id' => $accountId,
                // Tencent advertiser/get requires a pagination mode and page size
                // even when querying a single advertiser ID.
                'pagination_mode' => 'PAGINATION_MODE_NORMAL',
                'page' => 1,
                'page_size' => 100,
                'fields' => json_encode(['account_id']),
                'access_token' => $this->getToken(),
                'timestamp' => time(),
                'nonce' => bin2hex(random_bytes(16)),
            ],
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
            'allow_redirects' => false,
        ];
        // Opt-in single-run raw diagnostic log. Includes the real access token,
        // complete query, raw HTTP body and transport exception for local debugging.
        // Never enables logging for other Tencent API methods.
        $rawDebug = getenv('TX_ACCOUNT_PROBE_RAW_LOG') === '1';
        $rawTrace = $rawDebug ? bin2hex(random_bytes(6)) : '';
        if ($rawDebug) {
            $this->writeAdvertiserProbeRawLog($rawTrace, 'REQUEST',
                'GET https://api.e.qq.com/v3.0/advertiser/get?'
                . http_build_query($options['query'], '', '&', PHP_QUERY_RFC3986)
                . "\nOPTIONS=" . var_export($options, true));
        }
        try {
            $response = $this->getClient()->request(
                'GET', '/v3.0/advertiser/get', $options
            );
            $status = (int)$response->getStatusCode();
            $raw = (string)$response->getBody();
            if ($rawDebug) {
                $headers = method_exists($response, 'getHeaders')
                    ? var_export($response->getHeaders(), true) : 'unavailable';
                $this->writeAdvertiserProbeRawLog($rawTrace, 'RESPONSE',
                    "HTTP_STATUS=" . $status . "\nHEADERS=" . $headers . "\nRAW_BODY=\n" . $raw);
            }
        } catch (\Throwable $e) {
            if ($rawDebug) {
                $this->writeAdvertiserProbeRawLog($rawTrace, 'TRANSPORT_EXCEPTION',
                    get_class($e) . ": " . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
            throw new TencentAdvertiserProbeError(TencentAdvertiserProbeError::NETWORK_FAILED);
        }
        // Only numeric status and numeric API error code may leave this method.
        $data = json_decode($raw, true, 512, JSON_BIGINT_AS_STRING);
        $code = null;
        if (is_array($data) && isset($data['code'])
            && (is_int($data['code']) || is_string($data['code']))
            && preg_match('/^[0-9]{1,8}$/D', (string)$data['code'])) {
            $code = (int)$data['code'];
        }
        if ($status !== 200) {
            throw new TencentAdvertiserProbeError(
                TencentAdvertiserProbeError::HTTP_FAILED, $status, $code
            );
        }
        if (!is_array($data) || !array_key_exists('code', $data)) {
            throw new TencentAdvertiserProbeError(TencentAdvertiserProbeError::RESPONSE_INVALID);
        }
        if (!in_array($data['code'], [0, '0'], true)) {
            throw new TencentAdvertiserProbeError(
                TencentAdvertiserProbeError::API_REJECTED, $status, $code
            );
        }
        if (!is_array($data['data'] ?? null)
            || !is_array($data['data']['list'] ?? null)) {
            throw new TencentAdvertiserProbeError(TencentAdvertiserProbeError::RESPONSE_INVALID);
        }
        if (!$data['data']['list']) {
            throw new TencentAdvertiserProbeError(TencentAdvertiserProbeError::ACCOUNT_NOT_RETURNED);
        }
        $result = [];
        foreach ($data['data']['list'] as $item) {
            if (!is_array($item) || !isset($item['account_id'])
                || !is_scalar($item['account_id'])) {
                throw new TencentAdvertiserProbeError(TencentAdvertiserProbeError::RESPONSE_INVALID);
            }
            $result[] = ['account_id' => (string)$item['account_id']];
        }
        return $result;
    }

    /**
     * Temporary opt-in raw integration log, isolated to the account probe.
     * Contains sensitive parameters and unmodified remote response bytes.
     */
    private function writeAdvertiserProbeRawLog(string $trace, string $phase, string $details): void
    {
        $directory = BASE_PATH . '/runtime/logs';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            return;
        }
        $logFile = $directory . '/tencent-account-probe-raw.log';
        $entry = '[' . date('c') . ' pid=' . getmypid()
            . ' trace=' . $trace . ' phase=' . $phase . "]\n"
            . $details . "\n\n";
        $oldMask = umask(0077);
        try {
            @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            if (is_file($logFile)) {
                @chmod($logFile, 0600);
            }
        } finally {
            umask($oldMask);
        }
    }

    public function advertiserGet(string $accountId)
    {
        $data = $this->requestByToken('advertiser/get', [
            'account_id' => $accountId,
            'pagination_mode' => 'PAGINATION_MODE_NORMAL',
            'page' => 1,
            'page_size' => 100,
            'fields' => ['account_id', 'corporation_name'],
        ]);
        $list = [];
        foreach ($data['list'] ?? [] as $row) {
            if ((string)$row['account_id'] !== $accountId) {
                throw new Exception('腾讯直客授权返回了不匹配的账户');
            }
            $list[] = [
                'account_id' => $accountId,
                'advertiser_id' => $accountId,
                'advertiser_name' => $row['corporation_name'],
            ];
        }
        if (!$list) {
            throw new Exception('腾讯授权账户不可查询，请确认使用直客账号及接口权限');
        }
        return $list;
    }

    public function advertiserBudget(array $advertiserIds)
    {
        $list = [];
        foreach ($advertiserIds as $id) {
            $data = $this->requestByToken('advertiser_daily_budget/get', ['account_id' => (string)$id]);
            $list[] = ['advertiser_id' => (string)$id, 'date' => date('Y-m-d'), 'budget' => (float)$data['daily_budget'] / 100];
        }
        return $list;
    }

    public function planGet(string $advertiserId, int $page = 1, int $pageSize = 100, array $planIds = [], $isSearch = false)
    {
        $params = [
            'account_id' => $advertiserId,
            'pagination_mode' => 'PAGINATION_MODE_CURSOR',
            'page_size' => min(100, max(1, $pageSize)),
            'fields' => ['adgroup_id', 'adgroup_name', 'configured_status', 'daily_budget', 'begin_date', 'end_date', 'time_series', 'created_time', 'last_modified_time'],
        ];
        if ($planIds) {
            $params['filtering'] = [['field' => 'adgroup_id', 'operator' => 'IN', 'values' => array_map('strval', $planIds)]];
        }
        $list = [];
        $seenCursors = [];
        // 将游标分页转换为公共服务已有的 list / page_info 结构。
        do {
            $data = $this->requestByToken('adgroups/get', $params);
            foreach ($data['list'] ?? [] as $row) {
                $list[] = [
                    'advertiser_id' => $advertiserId,
                    'plan_id' => (string)$row['adgroup_id'],
                    'plan_name' => $row['adgroup_name'],
                    'put_status' => ['AD_STATUS_NORMAL' => 1, 'AD_STATUS_SUSPEND' => 2][$row['configured_status'] ?? ''] ?? 0,
                    'budget_mode' => empty($row['daily_budget']) ? 'BUDGET_MODE_INFINITE' : 'BUDGET_MODE_DAY',
                    'budget_amount' => (float)($row['daily_budget'] ?? 0) / 100,
                    'schedule_start' => $this->dateTime($row['begin_date'] ?? null),
                    'schedule_end' => $this->dateTime($row['end_date'] ?? null),
                    'schedule_time' => $row['time_series'] ?? '',
                    'create_time' => $this->dateTime($row['created_time'] ?? null),
                    'update_time' => $this->dateTime($row['last_modified_time'] ?? null),
                ];
            }
            $hasMore = !empty($data['cursor_page_info']['has_more']);
            if ($hasMore) {
                $cursor = (string)($data['cursor_page_info']['cursor'] ?? '');
                if ($cursor === '' || isset($seenCursors[$cursor])) {
                    throw new Exception('腾讯营销单元分页游标无效或重复');
                }
                $seenCursors[$cursor] = true;
                $params['cursor'] = $cursor;
            }
        } while ($hasMore);
        return ['list' => $list, 'page_info' => ['total_page' => 1]];
    }

    protected function dateTime($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $timestamp = is_numeric($value) ? (int)$value : strtotime($value);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    public function reportAdvertiserGet(string $advertiserId, string $date, $granularity = 'daily', $isSearch = false)
    {
        $list = [];
        $page = 1;
        do {
            $data = $this->report($advertiserId, $date, 'REPORT_LEVEL_ADVERTISER', $granularity, $page, 1000);
            $list = array_merge($list, $data['list']);
            $page++;
        } while ($page <= $data['page_info']['total_page']);
        if ($granularity !== 'daily' || count($list) <= 1) {
            return $list;
        }
        // A report request is scoped to one advertiser and one date. Never silently
        // persist only the first row if the upstream API returns multiple rows.
        $date = $list[0]['date'] ?? null;
        $advertiser = $list[0]['advertiser_id'] ?? null;
        foreach ($list as $row) {
            if (($row['date'] ?? null) !== $date || ($row['advertiser_id'] ?? null) !== $advertiser) {
                throw new Exception('腾讯广告主报表返回多个日期或账户，拒绝错误聚合');
            }
        }
        $combined = $list[0];
        foreach (array_slice($list, 1) as $row) {
            foreach (['cost', 'show', 'click', 'convert', 'active', 'next_day_open', 'key_action'] as $key) {
                if (array_key_exists($key, $row)) {
                    $combined[$key] = ($combined[$key] ?? 0) + $row[$key];
                }
            }
        }
        $divide = function ($value, $num) { return $num > 0 ? round($value / $num, 4) : 0; };
        $combined['avg_show_cost'] = $divide($combined['cost'] * 1000, $combined['show']);
        $combined['avg_click_cost'] = $divide($combined['cost'], $combined['click']);
        $combined['click_rate'] = $divide($combined['click'] * 100, $combined['show']);
        foreach (['convert' => 'click', 'active' => 'click', 'next_day_open' => 'active', 'key_action' => 'active'] as $key => $denominator) {
            if (isset($combined[$key])) {
                $combined[$key . '_cost'] = $divide($combined['cost'], $combined[$key]);
                $combined[$key . '_rate'] = $divide($combined[$key] * 100, $combined[$denominator]);
            }
        }
        return [$combined];
    }

    public function reportPlanGet(string $advertiserId, string $date, array $planIds = [], $granularity = 'daily', int $page = 1, int $pageSize = 100, $isSearch = false)
    {
        return $this->report($advertiserId, $date, 'REPORT_LEVEL_ADGROUP', $granularity, $page, $pageSize, $planIds);
    }

    protected function report(string $id, string $date, string $level, string $granularity, int $page, int $pageSize, array $planIds = []): array
    {
        $hourly = $granularity === 'hourly';
        $fields = ['date', 'cost', 'view_count', 'valid_click_count', 'conversions_count', 'activated_count'];
        // 不将总转化数冒充关键行为。特殊转化指标由广告主已有 extra 指定。
        $extraFields = $this->getAppInfo()['tx_report_fields'] ?? [];
        if (!is_array($extraFields)) {
            throw new Exception('腾讯 tx_report_fields 必须是指标映射对象');
        }
        foreach (['next_day_open', 'key_action'] as $key) {
            if (!empty($extraFields[$key])) {
                if (!is_string($extraFields[$key]) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $extraFields[$key])) {
                    throw new Exception('腾讯报表指标字段无效：' . $key);
                }
                $fields[] = $extraFields[$key];
            }
        }
        $groupBy = ['date'];
        if ($level === 'REPORT_LEVEL_ADGROUP') {
            $fields[] = 'adgroup_id';
            $groupBy[] = 'adgroup_id';
        }
        if ($hourly) {
            $fields[] = 'hour';
            $groupBy[] = 'hour';
        }
        $params = [
            'account_id' => $id, 'level' => $level,
            'date_range' => ['start_date' => $date, 'end_date' => $date],
            'time_line' => 'REQUEST_TIME', 'fields' => array_values(array_unique($fields)),
            'group_by' => $groupBy, 'page' => $page, 'page_size' => min(2000, max(1, $pageSize)),
        ];
        if ($planIds) {
            $params['filtering'] = [['field' => 'adgroup_id', 'operator' => 'IN', 'values' => array_map('strval', $planIds)]];
        }
        $data = $this->requestByToken($hourly ? 'hourly_reports/get' : 'daily_reports/get', $params);
        $totalPage = (int)($data['page_info']['total_page'] ?? 1);
        if ($totalPage * $params['page_size'] > 20000 || ($hourly && $totalPage > 100)) {
            throw new Exception('腾讯报表超过同步查询上限，请缩小账户或计划查询范围');
        }
        $list = [];
        foreach ($data['list'] ?? [] as $row) {
            $item = $this->formatReport($row, $extraFields);
            $item['date'] = $row['date'] ?? $date;
            $item['advertiser_id'] = $id;
            if ($level === 'REPORT_LEVEL_ADGROUP') {
                $item['plan_id'] = (string)$row['adgroup_id'];
            }
            if ($hourly) {
                $item['hour'] = sprintf('%02d', $row['hour']);
            }
            $list[] = $item;
        }
        return ['list' => $list, 'page_info' => ['total_page' => $totalPage]];
    }

    protected function formatReport(array $row, array $extraFields): array
    {
        $data = ['cost' => (float)($row['cost'] ?? 0) / 100];
        $map = ['show' => 'view_count', 'click' => 'valid_click_count', 'convert' => 'conversions_count', 'active' => 'activated_count'];
        foreach (['next_day_open', 'key_action'] as $key) {
            if (!empty($extraFields[$key])) {
                $map[$key] = $extraFields[$key];
            }
        }
        foreach ($map as $key => $field) {
            $data[$key] = (int)($row[$field] ?? 0);
        }
        $divide = function ($value, $num) { return $num > 0 ? round($value / $num, 4) : 0; };
        $data['avg_show_cost'] = $divide($data['cost'] * 1000, $data['show']);
        $data['avg_click_cost'] = $divide($data['cost'], $data['click']);
        $data['click_rate'] = $divide($data['click'] * 100, $data['show']);
        foreach (['convert' => 'click', 'active' => 'click', 'next_day_open' => 'active', 'key_action' => 'active'] as $key => $denominator) {
            if (isset($data[$key])) {
                $data[$key . '_cost'] = $divide($data['cost'], $data[$key]);
                $data[$key . '_rate'] = $divide($data[$key] * 100, $data[$denominator]);
            }
        }
        return $data;
    }

    public function planUpdate(string $advertiserId, string $planId, array $params = [])
    {
        throw new Exception('腾讯营销投放配置仅支持查询');
    }

    public function planUpdateStatus(string $advertiserId, string $planId, string $putStatus)
    {
        throw new Exception('腾讯营销不支持广告启停');
    }

    public function planUpdateBudget(string $advertiserId, string $planId, float $budgetAmount)
    {
        throw new Exception('腾讯营销预算仅支持查询');
    }

    public function reportProjectGet(string $advertiserId, string $date)
    {
        return ['list' => [], 'page_info' => ['total_page' => 0]];
    }

    public function reportAdGet(string $advertiserId, string $date)
    {
        return ['list' => [], 'page_info' => ['total_page' => 0]];
    }
}
