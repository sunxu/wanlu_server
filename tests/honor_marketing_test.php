<?php
/** 荣耀适配器隔离协议测试，不请求外部网络。 */
namespace GuzzleHttp {
    class Client {
        public $responses = [];
        public $requests = [];
        public function request($method, $uri, $options) {
            $this->requests[] = [$method, $uri, $options];
            $body = array_shift($this->responses);
            if ($body instanceof \Throwable) { throw $body; }
            return new class($body) {
                private $body;
                public function __construct($body) { $this->body = $body; }
                public function getBody() { return $this->body; }
            };
        }
    }
}
namespace {
    date_default_timezone_set('Asia/Shanghai');
    require dirname(__DIR__) . '/admin_server/app/Library/Mkt/MktAbstract.php';
    require dirname(__DIR__) . '/admin_server/app/Library/Mkt/MktHonor.php';
    require dirname(__DIR__) . '/admin_server/app/Constants/MktConst.php';
    require dirname(__DIR__) . '/api_server/app/Constants/AdConst.php';
    function ensure($condition, $message) { if (!$condition) { throw new \RuntimeException($message); } }
    function rejects($call) { try { $call(); } catch (\Exception $e) { return $e; } throw new \RuntimeException('预期拒绝无效响应或操作'); }
    function ok($data) { return json_encode(['code' => 0, 'data' => $data]); }
    function report($rows) { return ok(['adPageResponse' => ['code' => 0, 'data' => $rows]]); }
    function create_uuid(): string { return '00000000-0000-4000-8000-000000000001'; }
    class HonorAdapter extends \App\Library\Mkt\MktHonor {
        public $http;
        public $hosts = [];
        public function __construct() { $this->http = new \GuzzleHttp\Client(); }
        public function getClient(string $baseUri = null): \GuzzleHttp\Client { $this->hosts[] = $baseUri; return $this->http; }
    }
    $tests = [];
    $tests['沿用客户端凭证刷新'] = function () {
        $mkt = (new HonorAdapter())->setApp('client', 'private-secret');
        foreach (['', 'unused-old-token'] as $refreshToken) {
            $mkt->http->responses[] = json_encode(['token_type' => 'Bearer', 'access_token' => 'token', 'expires_in' => 3600]);
            $token = $mkt->authRefreshToken($refreshToken);
            ensure($token['access_token'] === 'Bearer token' && $token['access_time'] >= time() + 3599, '令牌或过期时间错误');
            ensure(!isset($token['refresh_token']) && $token['app_id'] === 'client', '不能依赖刷新令牌');
        }
        ensure($mkt->hosts === ['https://iam.developer.honor.com', 'https://iam.developer.honor.com'], '授权域名错误');
        ensure($mkt->http->requests[1][2]['form_params'] === ['grant_type' => 'client_credentials', 'client_id' => 'client', 'client_secret' => 'private-secret'], '刷新必须复用客户端凭证');
        $mkt->http->responses[] = json_encode(['token_type' => 'Bearer', 'access_token' => 'new', 'expires_in' => 3600, 'refresh_token' => 'optional']);
        ensure($mkt->authRefreshToken('')['refresh_token'] === 'optional', '应保留原适配器的可选刷新令牌返回值');
        $mkt->http->responses[] = '{}';
        rejects(function () use ($mkt) { $mkt->authRefreshToken(''); });
    };
    $tests['列表分页、长ID和广告组预算'] = function () {
        $mkt = (new HonorAdapter())->setToken('Bearer token');
        $mkt->http->responses[] = ok(['totalCount' => 101, 'adCampaignStatcVos' => [[
            'adCampaignId' => '9007199254740993', 'adCampaignName' => '荣耀项目', 'adCampaignStatus' => 0,
            'createDateTime' => '2026-10-20T12:01:00', 'updateDateTime' => '2026-10-21 13:00:00',
        ]]]);
        $page = $mkt->projectGet('123', 2, 100);
        ensure($page['page_info']['total_page'] === 2 && $page['list'][0]['project_id'] === '9007199254740993', '分页或长ID错误');
        ensure($page['list'][0]['put_status'] === 2 && $page['list'][0]['create_time'] === '2026-10-20 12:01:00', '状态或日期错误');
        $mkt->http->responses[] = ok(['totalCount' => 1, 'data' => [[
            'adGroupId' => '9', 'adCampaignId' => '8', 'groupName' => '荣耀广告', 'adGroupStatus' => 1, 'adDayBudget' => 123450000,
        ]]]);
        $ad = $mkt->adGet('123', 1, 10, ['9'])['list'][0];
        ensure($ad['budget_amount'] === 123.45 && $ad['project_id'] === '8' && $ad['put_status'] === 1, '广告组映射错误');
        $options = $mkt->http->requests[1][2];
        ensure($options['json']['ids'] === ['9'] && $options['headers']['Authorization'] === 'Bearer token', '筛选或令牌请求头错误');
        ensure(strlen($options['headers']['x-request-id']) === 36 && $mkt->hosts[1] === 'https://ads.cloud.honor.com', '请求ID或业务域名错误');
        $mkt->http->responses[] = ok(['totalCount' => 0, 'data' => []]);
        ensure($mkt->adGet('123')['list'] === [], '空列表应正常返回');
        $mkt->http->responses[] = ok(['totalCount' => 10, 'data' => []]);
        rejects(function () use ($mkt) { $mkt->adGet('123'); });
    };
    $tests['日报金额、实际行数和层级关系'] = function () {
        $mkt = (new HonorAdapter())->setToken('Bearer token');
        $mkt->http->responses[] = report([['adGroupId' => '9', 'metrics' => [
            'campaignId' => '8', 'adBilling' => '123450000', 'impression' => 1000, 'click' => 50,
            'aveClick' => 2469000, 'activate' => 5, 'activateCost' => 24690000, 'retain' => 3,
        ]]]);
        $result = $mkt->reportAdGet('123', '2026-10-21');
        $row = $result['list'][0];
        ensure($row['cost'] === 123.45 && $row['avg_click_cost'] === 2.469 && $row['active_cost'] === 24.69, '百万分之一元换算错误');
        ensure($result['page_info']['total_number'] === 1 && $row['project_id'] === '8' && $row['ad_id'] === '9', '报表行数或层级错误');
        ensure(!isset($row['key_action']) && !isset($row['convert']), '不得补造不支持的转化指标');
        ensure($mkt->http->requests[0][2]['json']['isQryAll'] === 1, '报表应请求全量');
        $mkt->http->responses[] = report([['adCampaignId' => '8', 'metrics' => []]]);
        ensure($mkt->reportProjectGet('123', '2026-10-21')['list'][0]['cost'] === 0.0, '缺失指标必须有零消耗默认值');
        ensure($mkt->reportProjectGet('123', '2026-10-21', [], 'daily', 2)['list'] === [], '全量报表不应重复请求第二页');
    };
    $tests['小时字段兼容和同粒度聚合'] = function () {
        $mkt = (new HonorAdapter())->setToken('Bearer token');
        $mkt->http->responses[] = report([
            ['statcHour' => 0, 'metrics' => ['adBilling' => 1000000, 'impression' => 100, 'click' => 10, 'activate' => 1]],
            ['hour' => '00:00:00', 'metrics' => ['adBilling' => 2000000, 'impression' => 100, 'click' => 10, 'activate' => 2]],
            ['statcHour' => 23, 'metrics' => ['adBilling' => 1000000]],
        ]);
        $rows = $mkt->reportAdvertiserGet('123', '2026-10-21', 'hourly');
        ensure(count($rows) === 2 && $rows[0]['hour'] === '00' && $rows[1]['hour'] === '23', '小时编号错误');
        ensure($rows[0]['cost'] === 3.0 && $rows[0]['active'] === 3.0 && $rows[0]['active_cost'] === 1.0, '小时不能覆盖同小时多行');
        ensure($mkt->http->requests[0][2]['json']['timeDimension'] === 1, '小时粒度错误');
        $mkt->http->responses[] = report([['metrics' => []]]);
        rejects(function () use ($mkt) { $mkt->reportAdvertiserGet('123', '2026-10-21', 'hourly'); });
        $mkt->http->responses[] = report([['metrics' => ['adBilling' => 1000000]], ['metrics' => ['adBilling' => 2000000]]]);
        ensure($mkt->reportAdvertiserGet('123', '2026-10-21')[0]['cost'] === 3.0, '广告主日报不能只取第一条响应');
    };
    $tests['业务错误、异常响应和密钥隔离'] = function () {
        $mkt = (new HonorAdapter())->setToken('secret-token');
        foreach (['invalid-json', json_encode(['code' => 100, 'message' => 'secret-token']), ok(['adPageResponse' => ['code' => 5]]), ok([]),
            ok(['adPageResponse' => ['code' => 0, 'totalCount' => 2, 'data' => []]]), new \Exception('secret-token')] as $body) {
            $mkt->http->responses[] = $body;
            $e = rejects(function () use ($mkt) { $mkt->reportAdvertiserGet('123', '2026-10-21'); });
            ensure(strpos($e->getMessage(), 'secret-token') === false, '异常不能泄漏令牌');
        }
        $mkt->http->responses[] = report([]);
        ensure($mkt->reportAdvertiserGet('123', '2026-10-21') === [], '合法空报表应返回空数组');
    };
    $tests['保留原适配器批量状态接口'] = function () {
        $mkt = (new HonorAdapter())->setToken('Bearer token');
        $mkt->http->responses = [ok([]), ok(null)];
        $result = $mkt->adBatchUpdateStatus('123', [['ad_id' => '1', 'status' => 'enable'], ['ad_id' => '2', 'status' => 'enable']]);
        ensure($result['ad_ids'] === ['1', '2'], '批量启停返回ID错误');
        ensure($mkt->http->requests[0][2]['json'] === ['adGroupIdList' => ['1', '2'], 'adGroupStatus' => 1], '批量启用请求错误');
        $mkt->adBatchUpdateStatus('123', [['ad_id' => '3', 'status' => 'disable']]);
        ensure($mkt->http->requests[1][2]['json'] === ['adGroupIdList' => ['3'], 'adGroupStatus' => 0], '批量暂停请求错误');
        foreach ([[], [['ad_id' => '1', 'status' => 'wrong']], [['ad_id' => '1', 'status' => 'enable'], ['ad_id' => '2', 'status' => 'disable']]] as $rows) {
            rejects(function () use ($mkt, $rows) { $mkt->adBatchUpdateStatus('123', $rows); });
        }
        ensure(count($mkt->http->requests) === 2, '无效或混合状态不能发请求');
        rejects(function () use ($mkt) { $mkt->planGet('123'); });
        rejects(function () use ($mkt) { $mkt->planUpdateBudget('123', '9', 1); });
    };
    $tests['平台编号隔离和完整抽象契约'] = function () {
        ensure(\App\Constants\MktConst::PLATFORM_SOURCE['honor'] === 7 && \App\Constants\MktConst::PLATFORM_SOURCE['tx'] === 8, '平台来源冲突');
        ensure(\App\Constants\AdConst::PUT_SOURCE_MAP['honor'] === 7, '管理端与归因端编号不一致');
        ensure(!((new \ReflectionClass(\App\Library\Mkt\MktHonor::class))->isAbstract()), '荣耀适配器未实现当前抽象契约');
    };
    foreach ($tests as $name => $test) { $test(); echo '通过：' . $name . PHP_EOL; }
    echo '共通过 ' . count($tests) . ' 组荣耀协议测试。' . PHP_EOL;
}
