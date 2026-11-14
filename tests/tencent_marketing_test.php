<?php
/** 隔离协议测试：php tests/tencent_marketing_test.php；不访问数据库或腾讯接口。 */

namespace GuzzleHttp {
    class Client
    {
        public $responses = [];
        public $requests = [];
        public function request($method, $uri, $options)
        {
            $this->requests[] = [$method, $uri, $options];
            $response = array_shift($this->responses);
            if ($response instanceof \Throwable) {
                throw $response;
            }
            return new class($response) {
                private $body;
                public function __construct($body) { $this->body = $body; }
                public function getBody() { return $this->body; }
            };
        }
    }
}

namespace App\Model\Mkt {
    class MktApp
    {
        public static $apps = [];
        public static function find($id) { return self::$apps[$id] ?? null; }
    }
}

namespace App\Utils {
    class RedisManager
    {
        public static $redis;
        public static function get() { return self::$redis; }
    }
}

namespace App\Service\Mkt {
    // 授权 state 测试仅记录分发结果，不执行数据库持久化。
    class MktService
    {
        public static $calls = [];
        public function authUser($appId, $code) { self::$calls[] = [$appId, $code]; }
    }
}

namespace {
    class DB
    {
        public static $row;
        public static $rows = [];
        public static $whereCalls = [];
        public static function connection($name) { check($name === 'uc', '腾讯授权必须来自 uc 数据库'); return new class {
            private $tableName = '';
            public function table($name) { $this->tableName = $name; return $this; }
            public function join(...$args) { return $this; }
            public function where(...$args) { \DB::$whereCalls[] = [$this->tableName, $args]; return $this; }
            public function first($columns) {
                return array_key_exists($this->tableName, \DB::$rows) ? \DB::$rows[$this->tableName] : \DB::$row;
            }
        }; }
    }
    class Log
    {
        public static function info($message, $context) {}
    }

    date_default_timezone_set('Asia/Shanghai');
    $root = dirname(__DIR__);
    require $root . '/admin_server/app/Library/Mkt/MktAbstract.php';
    require $root . '/admin_server/app/Library/Mkt/MktTx.php';
    require $root . '/admin_server/app/Constants/MktConst.php';
    require $root . '/admin_server/app/Service/Mkt/TencentAuthService.php';
    require $root . '/admin_server/app/Service/Mkt/TxPrivateCredentialId.php';
    require $root . '/api_server/app/Services/Mkt/TencentCallbackUrl.php';
    require $root . '/api_server/app/Services/Mkt/TencentClick.php';
    require $root . '/api_server/app/Services/Mkt/TencentService.php';
    require $root . '/api_server/app/Constants/AdConst.php';

    function make($class) { return new $class(); }
    function check($condition, $message) {
        if (!$condition) { throw new \RuntimeException($message); }
    }
    function rejects($call, $message) {
        try { $call(); } catch (\Exception $e) { return $e; }
        throw new \RuntimeException($message);
    }
    function ok($data) { return json_encode(['code' => 0, 'data' => $data]); }

    class TestTx extends \App\Library\Mkt\MktTx
    {
        public $http;
        public function __construct() { $this->http = new \GuzzleHttp\Client(); }
        public function getClient(string $baseUri = null): \GuzzleHttp\Client { return $this->http; }
    }
    $tests = [];
    $tests['请求编码、金额和比率'] = function () {
        $tx = (new TestTx())->setToken('test-token');
        $tx->http->responses[] = ok(['list' => [[
            'date' => '2026-10-21', 'adgroup_id' => '9007199254740993', 'cost' => 12345,
            'view_count' => 1000, 'valid_click_count' => 50, 'conversions_count' => 10, 'activated_count' => 5,
        ]], 'page_info' => ['total_page' => 1]]);
        $result = $tx->reportPlanGet('123', '2026-10-21');
        $row = $result['list'][0];
        check($row['cost'] === 123.45 && $row['active_cost'] === 24.69 && $row['click_rate'] === 5.0, '金额或比率转换错误');
        check($row['plan_id'] === '9007199254740993', '长 ID 丢失精度');
        check(!isset($row['key_action']), '不应把总转化数冒充关键行为');
        $query = $tx->http->requests[0][2]['query'];
        check(json_decode($query['group_by'], true) === ['date', 'adgroup_id'], '聚合参数编码错误');
        check(strlen($query['nonce']) === 32 && $query['access_token'] === 'test-token', '通用请求参数缺失');
    };
    $tests['多行广告主日报合并且小时数据不合并'] = function () {
        $tx = (new TestTx())->setToken('test');
        $tx->http->responses[] = ok(['list' => [
            ['date' => '2026-10-21', 'cost' => 100, 'view_count' => 10, 'valid_click_count' => 2, 'conversions_count' => 1, 'activated_count' => 1],
            ['date' => '2026-10-21', 'cost' => 200, 'view_count' => 30, 'valid_click_count' => 6, 'conversions_count' => 2, 'activated_count' => 1],
        ], 'page_info' => ['total_page' => 1]]);
        $daily = $tx->reportAdvertiserGet('123', '2026-10-21');
        check(count($daily) === 1 && $daily[0]['cost'] === 3.0 && $daily[0]['show'] === 40 && $daily[0]['click_rate'] === 20.0, '多行日报未完整汇总或比率计算错误');
        $tx->http->responses[] = ok(['list' => [
            ['date' => '2026-10-21', 'hour' => 1, 'cost' => 100],
            ['date' => '2026-10-21', 'hour' => 2, 'cost' => 200],
        ], 'page_info' => ['total_page' => 1]]);
        $hourly = $tx->reportAdvertiserGet('123', '2026-10-21', 'hourly');
        check(count($hourly) === 2 && $hourly[0]['hour'] === '01' && $hourly[1]['hour'] === '02', '小时报表不得按日报方式合并');
        $tx->http->responses[] = ok(['list' => [
            ['date' => '2026-10-21', 'cost' => 100],
            ['date' => '2026-10-22', 'cost' => 200],
        ], 'page_info' => ['total_page' => 1]]);
        rejects(function () use ($tx) { $tx->reportAdvertiserGet('123', '2026-10-21'); }, '跨日期报表不得错误合并');
    };
    $tests['零分母与配置化转化指标'] = function () {
        $tx = (new TestTx())->setToken('test')->setAppInfo(['tx_report_fields' => ['key_action' => 'key_page_view_count', 'next_day_open' => 'test_retained_count']]);
        $tx->http->responses[] = ok(['list' => [['cost' => 0, 'key_page_view_count' => 3, 'test_retained_count' => 2]], 'page_info' => ['total_page' => 1]]);
        $row = $tx->reportAdvertiserGet('123', '2026-10-21')[0];
        check($row['active_rate'] === 0 && $row['key_action'] === 3 && $row['next_day_open'] === 2, '零分母或指标映射错误');
    };
    $tests['小时报表跨页与小时编号'] = function () {
        $tx = (new TestTx())->setToken('test');
        foreach ([1, 2] as $page) {
            $tx->http->responses[] = ok(['list' => [['cost' => 100, 'hour' => $page]], 'page_info' => ['total_page' => 2]]);
        }
        $rows = $tx->reportAdvertiserGet('123', '2026-10-21', 'hourly');
        check(count($rows) === 2 && $rows[0]['hour'] === '01' && $rows[1]['hour'] === '02', '小时分页错误');
        check($tx->http->requests[1][1] === '/v3.0/hourly_reports/get', '小时报表调用了日报接口');
    };
    $tests['空报表与频控失败'] = function () {
        $tx = (new TestTx())->setToken('test');
        $tx->http->responses[] = ok(['list' => [], 'page_info' => ['total_page' => 0]]);
        check($tx->reportAdvertiserGet('123', '2026-10-21') === [], '空报表格式错误');
        $tx->http->responses[] = json_encode(['code' => 100, 'message_cn' => '频控']);
        rejects(function () use ($tx) { $tx->reportAdvertiserGet('123', '2026-10-21'); }, '接口失败不能被吞成空报表');
    };
    $tests['无效指标配置在请求前拒绝'] = function () {
        $tx = (new TestTx())->setToken('test')->setAppInfo(['tx_report_fields' => ['key_action' => ['invalid']]]);
        rejects(function () use ($tx) { $tx->reportAdvertiserGet('123', '2026-10-21'); }, '应拒绝无效指标映射');
        check(!$tx->http->requests, '错误配置不应发送请求');
    };
    $tests['网络异常不泄露令牌'] = function () {
        $tx = (new TestTx())->setToken('secret-token');
        $tx->http->responses[] = new \Exception('https://api.e.qq.com/example?access_token=secret-token');
        $error = rejects(function () use ($tx) { $tx->reportAdvertiserGet('123', '2026-10-21'); }, '网络失败必须报错');
        check(strpos($error->getMessage(), 'secret-token') === false, '网络异常泄露了令牌');
    };
    $tests['计划游标、预算和重复游标'] = function () {
        $tx = (new TestTx())->setToken('test');
        $tx->http->responses[] = ok(['list' => [['adgroup_id' => 9, 'adgroup_name' => '计划', 'daily_budget' => 12345, 'configured_status' => 'AD_STATUS_SUSPEND']], 'cursor_page_info' => ['has_more' => true, 'cursor' => 'next']]);
        $tx->http->responses[] = ok(['list' => [['adgroup_id' => 10, 'adgroup_name' => '下一页计划']], 'cursor_page_info' => ['has_more' => false]]);
        $result = $tx->planGet('123');
        check($result['list'][0]['budget_amount'] === 123.45 && $result['list'][0]['put_status'] === 2, '计划转换错误');
        check(array_column($result['list'], 'plan_id') === ['9', '10'] && $result['page_info']['total_page'] === 1, '游标应在适配器内合并为公共分页格式');
        check(!isset($tx->http->requests[0][2]['query']['cursor']) && $tx->http->requests[1][2]['query']['cursor'] === 'next', '适配器未正确传递游标');
        foreach (['a', 'b', 'a'] as $cursor) {
            $tx->http->responses[] = ok(['list' => [], 'cursor_page_info' => ['has_more' => true, 'cursor' => $cursor]]);
        }
        rejects(function () use ($tx) { $tx->planGet('123'); }, '跨页循环游标必须终止');
        $tx->http->responses[] = ok(['list' => [], 'cursor_page_info' => ['has_more' => true]]);
        rejects(function () use ($tx) { $tx->planGet('123'); }, '有下一页却缺少游标必须终止');
        $tx->http->responses[] = ok(['list' => [], 'cursor_page_info' => ['has_more' => false]]);
        check($tx->planGet('123')['list'] === [], '重复同步的空结果应正常结束');
    };
    $tests['授权身份及刷新保留语义'] = function () {
        $tx = (new TestTx())->setApp('456', 'secret')->setAppInfo(['auth_url' => 'https://developers.e.qq.com/oauth/authorize?redirect_uri=https%3A%2F%2Fexample.com%2Fuc%2Fmkt%2Ftencent']);
        $tx->http->responses[] = ok(['access_token' => 'access', 'refresh_token' => 'refresh', 'access_token_expires_in' => 100, 'refresh_token_expires_in' => 1000, 'authorizer_info' => ['account_id' => 123, 'account_role_type' => 'ACCOUNT_ROLE_TYPE_ADVERTISER']]);
        $token = $tx->authAccessToken('code');
        check($token['user_id'] === 'tx_456_123' && $token['account_id'] === '123', '授权身份应来自实际账户');
        $tx->http->responses[] = ok(['access_token' => 'new', 'access_token_expires_in' => 100]);
        $refresh = $tx->authRefreshToken('refresh');
        check(!array_key_exists('refresh_token', $refresh) && !array_key_exists('refresh_time', $refresh), '刷新不能清空或延期旧刷新令牌');
    };
    $tests['拒绝代理账号与跨账户查询'] = function () {
        $tx = (new TestTx())->setApp('456', 'secret')->setToken('test')->setAppInfo(['auth_url' => 'https://developers.e.qq.com/oauth/authorize?redirect_uri=https%3A%2F%2Fexample.com%2Fuc%2Fmkt%2Ftencent']);
        $tx->http->responses[] = ok(['refresh_token' => 'refresh', 'authorizer_info' => ['account_id' => 123, 'account_role_type' => 'ACCOUNT_ROLE_TYPE_AGENCY']]);
        rejects(function () use ($tx) { $tx->authAccessToken('code'); }, '应拒绝代理商');
        $tx->http->responses[] = ok(['list' => [['account_id' => 999, 'corporation_name' => '其他账户']]]);
        rejects(function () use ($tx) { $tx->advertiserGet('123'); }, '不得同步非授权直客');
    };
    $tests['投放设置保持只读'] = function () {
        $tx = new TestTx();
        rejects(function () use ($tx) { $tx->planUpdate('1', '2', ['schedule_time' => '']); }, '不允许修改时段');
        rejects(function () use ($tx) { $tx->planUpdateStatus('1', '2', 'enable'); }, '不允许启停');
        rejects(function () use ($tx) { $tx->planUpdateBudget('1', '2', 100); }, '不允许改预算');
        check(!$tx->http->requests, '只读限制必须在发送网络请求前生效');
    };
    $tests['OAuth 发起用户绑定、两阶段确认及重放防护'] = function () {
        \App\Service\Mkt\MktService::$calls = [];
        \App\Utils\RedisManager::$redis = new class {
            public $data = [];
            public function setex($key, $ttl, $value) { check($ttl === 600, 'state 期限错误'); $this->data[$key] = $value; }
            public function get($key) { return $this->data[$key] ?? null; }
            public function eval($script, $args, $num) {
                $key = $args[0];
                $value = $this->data[$key] ?? null;
                if ($value === null) return false;
                if ($num === 2) {
                    $pending = json_decode($value, true);
                    $pending['code'] = $args[2];
                    $this->data[$args[1]] = json_encode($pending);
                    unset($this->data[$key]);
                    return 1;
                }
                if ($value !== $args[1]) return false;
                unset($this->data[$key]);
                return $value;
            }
        };
        \App\Model\Mkt\MktApp::$apps['456'] = new class {
            public $platform = 'tx';
            public function toArray() { return ['auth_url' => 'https://developers.e.qq.com/oauth/authorize?redirect_uri=https%3A%2F%2Fexample.com%2Fuc%2Fmkt%2Ftencent']; }
        };
        $service = new \App\Service\Mkt\TencentAuthService();
        rejects(function () use ($service) { $service->authorize('456', ''); }, '没有后台用户不得发起授权');
        parse_str(parse_url($service->authorize('456', '1001'), PHP_URL_QUERY), $query);
        check(strlen($query['state']) === 64 && $query['client_id'] === '456', '授权 URL 错误');
        check($service->complete($query['state'], '1001') === false, '回调前不得写入授权');
        $service->callback($query['state'], 'code');
        check(!\App\Service\Mkt\MktService::$calls, '公开回调不得执行 token 交换和保存');
        rejects(function () use ($service, $query) { $service->complete($query['state'], '1002'); }, '其他后台用户不得确认');
        check($service->complete($query['state'], '1001') === true, '原发起用户应能完成确认');
        check(\App\Service\Mkt\MktService::$calls === [['456', 'code']], '只允许原发起用户交换授权码');
        rejects(function () use ($service, $query) { $service->complete($query['state'], '1001'); }, '确认不得重放');
        rejects(function () use ($service, $query) { $service->callback($query['state'], 'code'); }, '回调 state 不得重放');
        rejects(function () use ($service) { $service->callback(str_repeat('f', 64), 'code'); }, '过期 state 必须拒绝');
    };
    $click = ['advertiser_id' => '123', 'adgroup_id' => '9', 'creative_id' => '7', 'click_id' => 'click-1', 'click_time' => (string)(time() - 60), 'oaid' => 'OAID-original', 'muid' => md5('123456789012345'), 'android_id' => md5('android')];
    $tests['DataNexus 点击监测宏与项目参数一致'] = function () use ($root) {
        $config = require $root . '/admin_server/config/autoload/ad.php';
        $tx = null;
        foreach ($config['source'] as $source) {
            if ($source['name'] === '腾讯营销') { $tx = $source; break; }
        }
        check(is_array($tx) && $tx['path'] === '/v1/ad/tx/%s?%s', '腾讯监测地址必须使用无尾斜杠的接口路径，避免 301 重定向');
        $required = [
            'advertiser_id' => '__ACCOUNT_ID__',
            'adgroup_id' => '__ADGROUP_ID__',
            'campaign_id' => '__CAMPAIGN_ID__',
            'creative_id' => '__AD_ID__',
            'click_id' => '__CLICK_ID__',
            'click_time' => '__CLICK_TIME__',
            'muid' => '__MUID__',
            'android_id' => '__HASH_ANDROID_ID__',
            'oaid' => '__OAID__',
            'hash_oaid' => '__HASH_OAID__',
            'promoted_object_id' => '__PROMOTED_OBJECT_ID__',
            'callback_url' => '__CALLBACK__',
            'device_os_type' => '__DEVICE_OS_TYPE__',
            'ip' => '__IP__',
            'user_agent' => '__USER_AGENT__',
        ];
        check($tx['params'] === $required, '腾讯点击参数与已确认宏必须一致');
        // DataNexus 验证器要求 CALLBACK 宏；当前转化服务仍使用独立 OAuth user_actions/add。
        check($tx['params']['callback_url'] === '__CALLBACK__', '必须包含 DataNexus CALLBACK 下发宏');
        // 与 api_server/routes/v1/api.php 的真实路由保持一致。
        $routes = file_get_contents($root . '/api_server/routes/v1/api.php');
        check(strpos($routes, "ad/tx/{appCode}") !== false, '腾讯监测 URL 必须匹配真实 API 路由');
        $generatedUrl = sprintf($tx['path'], 'bxllzs', urldecode(http_build_query($tx['params'])));
        check(strpos($generatedUrl, '/v1/ad/tx/bxllzs?') === 0, '不得生成会 301 跳转的尾斜杠 URL');
        check(strpos($generatedUrl, '/v1/ad/tx/bxllzs/?') === false, '不得回退到带尾斜杠地址');
        $query = urldecode(http_build_query($tx['params']));
        parse_str($query, $parsed);
        check($parsed['click_id'] === '__CLICK_ID__' && $parsed['oaid'] === '__OAID__', 'URL 编码不能破坏点击宏');
        foreach (['hash_oaid', 'promoted_object_id', 'callback_url', 'device_os_type'] as $key) {
            check($parsed[$key] === $required[$key], '腾讯要求的必填宏不可缺少：' . $key);
        }
    };
    $tests['点击保留 DataNexus Callback 且不破坏 OAID'] = function () {
        $params = [
            'advertiser_id' => '123', 'adgroup_id' => '9',
            'click_id' => 'macro-case', 'click_time' => (string)(time() - 10),
            'oaid' => 'OAID-original', 'hash_oaid' => md5('OAID-original'),
            'promoted_object_id' => 'promoted-1',
            'device_os_type' => '0',
            'callback_url' => 'https://api.e.qq.com/conv?cb=opaque-test&conv_id=123',
        ];
        $row = \App\Services\Mkt\TencentClick::normalize($params);
        check($row['oaid'] === 'OAID-original', 'HASH_OAID 宏不能覆盖原 OAID 归因');
        check($row['callback_url'] === $params['callback_url'], '必须保留下发的 Callback URL');
        check($row['click_id'] === 'macro-case' && $row['account_id'] === '123', '额外宏不能破坏点击归一化');
    };
    $tests['腾讯 OAID 原值和 MD5 不重复哈希'] = function () {
        $base = ['advertiser_id' => '123', 'adgroup_id' => '9', 'click_id' => 'oaid-case', 'click_time' => (string)(time() - 10)];
        $raw = 'OAID-original';
        $hashed = strtoupper(md5($raw));
        $rowRaw = \App\Services\Mkt\TencentClick::normalize($base + ['oaid' => $raw]);
        $rowHash = \App\Services\Mkt\TencentClick::normalize($base + ['oaid' => $hashed]);
        check($rowRaw['oaid'] === $raw, 'OAID 原值应保留用于归因');
        check($rowHash['oaid'] === strtolower($hashed), '已哈希 OAID 应归一化小写');
        $service = new \App\Services\Mkt\TencentService();
        $extra = ['user_action_set_id' => '456'];
        $rawData = $service->payload('activate', time() - 1, $rowRaw, $extra);
        $hashData = $service->payload('activate', time() - 1, $rowHash, $extra);
        check($rawData['actions'][0]['user_id']['hash_oaid'] === md5($raw), 'OAID 原值回传必须 MD5');
        check($hashData['actions'][0]['user_id']['hash_oaid'] === md5($raw), '已哈希 OAID 回传不得二次 MD5');
    };
    $tests['点击协议与哈希不重复计算'] = function () use ($click) {
        $data = \App\Services\Mkt\TencentClick::normalize($click);
        check($data['imei'] === $click['muid'] && $data['oaid'] === $click['oaid'] && $data['aid'] === '9', '点击字段或 MD5 转换错误');
        $millis = $click; $millis['click_time'] = $click['click_time'] . '000';
        check(\App\Services\Mkt\TencentClick::normalize($millis)['ts'] === $data['ts'], '点击毫秒转秒错误');
        $invalid = $click; unset($invalid['click_time']);
        rejects(function () use ($invalid) { \App\Services\Mkt\TencentClick::normalize($invalid); }, '不应以当前时间补造点击');
        $invalid = $click; $invalid['advertiser_id'] = ['123'];
        rejects(function () use ($invalid) { \App\Services\Mkt\TencentClick::normalize($invalid); }, '应拒绝数组参数');
    };
    $tests['腾讯原始 MUID 大小写与归因一致'] = function () {
        $click = ['advertiser_id' => '123', 'adgroup_id' => '9', 'click_id' => 'case-test', 'click_time' => (string)(time() - 10), 'muid' => 'AABBCCDD'];
        $row = \App\Services\Mkt\TencentClick::normalize($click);
        check($row['imei'] === md5('aabbccdd'), '原始 MUID 应按归因匹配规则转小写后哈希');
        $click['muid'] = md5('AABBCCDD');
        check(\App\Services\Mkt\TencentClick::normalize($click)['imei'] === md5('AABBCCDD'), '腾讯预哈希 MUID 不应重复哈希');
    };
    $tests['激活留存和关键行为回传幂等'] = function () use ($click) {
        $params = \App\Services\Mkt\TencentClick::normalize($click); $params['app_code'] = 'demo';
        $extra = ['user_action_set_id' => '456', 'key_action_type' => 'CUSTOM', 'custom_action' => 'key_action'];
        $service = new \App\Services\Mkt\TencentService();
        $time = time() - 10;
        $active = $service->payload('activate', $time, $params, $extra);
        check($active === $service->payload('activate', $time, $params, $extra), '重试必须保持幂等标识');
        check($active['actions'][0]['user_id']['hash_oaid'] === md5($click['oaid']), 'OAID 哈希错误');
        check($active['actions'][0]['user_id']['hash_imei'] === $click['muid'], 'IMEI 被重复 MD5');
        $keep = $service->payload('keep1', $time, $params, $extra);
        check($keep['actions'][0]['action_type'] === 'START_APP' && $keep['actions'][0]['outer_action_id'] !== $active['actions'][0]['outer_action_id'], '留存与激活应使用不同事件标识');
        check(($keep['actions'][0]['action_param']['length_of_stay'] ?? null) === 1, '次日留存必须带 length_of_stay=1');
        $key = $service->payload('key_action', $time, $params, $extra);
        check($key['actions'][0]['custom_action'] === 'key_action' && $key['actions'][0]['trace']['click_id'] === 'click-1', '关键行为配置或点击追踪错误');
        rejects(function () use ($service, $time, $params) { $service->payload('key_action', $time, $params, ['user_action_set_id' => 456]); }, '不得猜测关键行为');
        rejects(function () use ($service, $time, $params) { $service->payload('activate', $time, $params, []); }, '不得缺失行为源');
    };
    $tests['真实回传读取对象授权记录'] = function () {
        $service = new class extends \App\Services\Mkt\TencentService {
            public $requests = [];
            protected function request(string $url, array $payload): array {
                $this->requests[] = [$url, $payload];
                return ['code' => 0];
            }
        };
        $params = [
            'account_id' => '123', 'app_code' => 'demo', 'click_id' => 'click-1',
            'imei' => md5('123456789012345'),
        ];
        \DB::$row = (object)[
            'access_token' => 'dummy-token', 'access_time' => time() + 1800,
            'extra' => json_encode(['user_action_set_id' => '456']),
        ];
        check($service->callback('activate', time() - 1, $params) === true, '对象授权记录应可正常回传');
        check(count($service->requests) === 1 && $service->requests[0][1]['account_id'] === 123, '对象授权记录的回传参数错误');
        \DB::$row = ['access_token' => 'dummy-token', 'access_time' => time() + 1800, 'extra' => '{"user_action_set_id":"456"}'];
        check($service->callback('activate', time() - 1, $params) === true, '数组授权记录应兼容');
        \DB::$row = null;
        rejects(function () use ($service, $params) { $service->callback('activate', time() - 1, $params); }, '缺失授权记录必须拒绝');
        check(count($service->requests) === 2, '缺失授权记录不得发送回传');
    };
    $tests['私有凭证 ID 不含 OAuth 身份且与管理端一致'] = function () {
        $a = \App\Service\Mkt\TxPrivateCredentialId::fromAppCode('bxllzs');
        $b = \App\Services\Mkt\TencentService::privateCredentialId('bxllzs');
        check($a === $b && strlen($a) === 46 && strpos($a, 'txapp_') === 0, '两端凭证 ID 生成规则必须一致');
        check($a !== \App\Services\Mkt\TencentService::privateCredentialId('lzgjf'), '不同 App 的凭证不得相同');
        rejects(function () { \App\Service\Mkt\TxPrivateCredentialId::fromAppCode('../invalid'); }, '非法 App Code 不得生成凭证');
        rejects(function () { \App\Services\Mkt\TencentService::privateCredentialId('Bad'); }, '客户端非法 App Code 必须拒绝');
    };
    $tests['Callback 目标必须是腾讯授权的 HTTPS URL'] = function () {
        $valid = 'https://api.e.qq.com/conv?cb=opaque%2Btext&conv_id=123';
        \App\Services\Mkt\TencentCallbackUrl::assertPostTarget($valid);
        \App\Services\Mkt\TencentCallbackUrl::assertClickTarget('http://tracking.e.qq.com/conv?cb=opaque&conv_id=123');
        foreach ([
            'http://tracking.e.qq.com/conv?cb=opaque&conv_id=123',
            'https://api.e.qq.com.evil.test/conv?cb=opaque&conv_id=123',
            'https://api.e.qq.com@evil.test/conv?cb=opaque&conv_id=123',
            'https://api.e.qq.com:8443/conv?cb=opaque&conv_id=123',
            'https://api.e.qq.com/other?cb=opaque&conv_id=123',
            'https://api.e.qq.com/conv?cb=opaque',
        ] as $invalid) {
            rejects(function () use ($invalid) { \App\Services\Mkt\TencentCallbackUrl::assertPostTarget($invalid); }, '恶意 Callback 目标必须拒绝');
        }
    };
    $tests['按 App 读取独立 Token 并使用真正 DataNexus Callback'] = function () {
        putenv('TX_DATANEXUS_CALLBACK_APPS=demo');
        $service = new class extends \App\Services\Mkt\TencentService {
            public $sent = [];
            protected function requestCallback(string $url, array $payload, string $accessToken): array {
                $this->sent[] = [$url, $payload, $accessToken];
                return ['code' => 0, 'trace_id' => 'test-trace'];
            }
        };
        \DB::$rows = [
            'mkt_advertiser as a' => (object)['extra' => '{}'],
            'mkt_user as c' => (object)['access_token' => 'private-demo-token', 'access_time' => time() + 2000],
        ];
        \DB::$whereCalls = [];
        $params = [
            'account_id' => '123', 'app_code' => 'demo', 'click_id' => 'click-private-1',
            'oaid' => 'OAID-original',
            'callback_url' => 'https://api.e.qq.com/conv?cb=opaque&conv_id=123',
        ];
        $when = time() - 30;
        check($service->callback('activate', $when, $params) === true, '正确绑定及有效 Token 应可完成 Callback');
        check(count($service->sent) === 1
            && $service->sent[0][0] === $params['callback_url']
            && $service->sent[0][2] === 'private-demo-token', '必须使用实际下发 URL 和当前 App 的 Token');
        check(array_keys($service->sent[0][1]) === ['actions']
            && $service->sent[0][1]['actions'][0]['action_type'] === 'ACTIVATE_APP', 'Callback 不能发送非 Callback 行为源字段');
        check($service->callback('activate', $when, $params) === true
            && $service->sent[0][1] === $service->sent[1][1], '失败重试必须维持事件幂等标识');
        $ids = array_values(array_filter(\DB::$whereCalls, function ($item) {
            return $item[0] === 'mkt_user as c' && $item[1][0] === 'c.user_id';
        }));
        check($ids && $ids[0][1][1] === \App\Services\Mkt\TencentService::privateCredentialId('demo'),
            '不能按 mkt_account.user_id 取 Token');
        \DB::$rows['mkt_user as c'] = null;
        rejects(function () use ($service, $params, $when) { $service->callback('activate', $when, $params); }, '缺失私有 Token 必须阻止上报');
        \DB::$rows['mkt_user as c'] = ['access_token' => 'private-demo-token', 'access_time' => time() + 2000];
        $params['callback_url'] = 'https://evil.test/conv?cb=opaque&conv_id=123';
        rejects(function () use ($service, $params, $when) { $service->callback('activate', $when, $params); }, '不能向第三方发送 Token');
        check(count($service->sent) === 2, '凭证错误或恶意 URL 不得发送网络请求');
        \DB::$rows = [];
        putenv('TX_DATANEXUS_CALLBACK_APPS');
    };

    $tests['无鉴权 HTTP Callback 使用真实点击 URL 和稳定激活数据'] = function () {
        putenv('TX_DATANEXUS_CALLBACK_APPS=demo');
        $service = new class extends \\App\\Services\\Mkt\\TencentService {
            public $sent = [];
            public $reply = ['code' => 0, 'trace_id' => 'safe_trace_1'];
            protected function requestUnauthenticatedCallback(string $url, array $payload): array {
                $this->sent[] = [$url, $payload];
                return $this->reply;
            }
        };
        \\DB::$rows = ['mkt_advertiser as a' => (object)['extra' => '{}']];
        \\DB::$row = null;
        \\DB::$whereCalls = [];
        $params = [
            'account_id' => '95833136', 'app_code' => 'demo',
            'click_id' => 'g467q2rdaaaihuj7rxaq', 'oaid' => '1779f882-367e-44d4-90d4-dd3ec6810015',
            'callback_url' => 'http://tracking.e.qq.com/conv?cb=opaque%2Bcallback%3D&conv_id=97228665',
        ];
        $when = 1794654992;
        check($service->callback('activate', $when, $params) === true,
            'HTTP Callback 的有效 code=0 必须判为成功');
        check($service->sent[0][0] === $params['callback_url'],
            '必须原样使用点击下发的 URL，不得改写 cb 或 conv_id');
        $action = $service->sent[0][1]['actions'][0];
        check($action['action_type'] === 'ACTIVATE_APP'
            && $action['action_time'] === $when
            && $action['user_id']['hash_oaid'] === md5($params['oaid'])
            && $action['trace']['click_id'] === $params['click_id'],
            'HTTP Callback 事件及 OAID 映射必须与原有协议一致');
        check(array_keys($service->sent[0][1]) === ['actions'],
            'HTTP Callback 不需要 user_action_set_id 或 account_id');
        check($service->callback('activate', $when, $params) === true
            && $service->sent[0][1] === $service->sent[1][1],
            '重试必须保持 outer_action_id 与整个事件数据不变');
        check(strlen($action['outer_action_id']) < 255, '幂等标识超长');
        check(!array_filter(\\DB::$whereCalls, function ($query) {
            return $query[0] === 'mkt_user as c';
        }), 'HTTP 无鉴权模式禁止读取私有 Token');
        $service->reply = ['code' => 12201, 'trace_id' => 'safe_trace_2'];
        check($service->callback('activate', $when, $params) === false,
            'HTTP Callback 非零业务码不得判成功');
        $params['callback_url'] = 'https://evil.test/conv?cb=opaque&conv_id=97228665';
        rejects(function () use ($service, $when, $params) {
            $service->callback('activate', $when, $params);
        }, '恶意 Callback 必须在请求前阻止');
        check(count($service->sent) === 3, '恶意 URL 不得触发真实 Callback');
        \\DB::$rows = [];
        putenv('TX_DATANEXUS_CALLBACK_APPS');
    };
    $tests['HTTP Callback 传输不含 Token 且限制协议和重定向'] = function () {
        $service = new class extends \\App\\Services\\Mkt\\TencentService {
            public $captures = [];
            public function simulate(string $url): array {
                return $this->requestUnauthenticatedCallback($url, ['actions' => [['action_type' => 'ACTIVATE_APP']]]);
            }
            protected function sendCallbackHttp(string $url, array $payload, array $headers, int $protocol): array {
                $this->captures[] = compact('url', 'payload', 'headers', 'protocol');
                return ['code' => 0];
            }
        };
        $good = 'http://tracking.e.qq.com/conv?cb=x%252By&conv_id=97228665';
        check($service->simulate($good)['code'] === 0, '无鉴权 Callback 应支持合法 HTTP URL');
        check($service->captures[0]['url'] === $good
            && $service->captures[0]['protocol'] === CURLPROTO_HTTP,
            'HTTP Callback 必须仅允许 HTTP 协议');
        $headerLine = implode(' | ', $service->captures[0]['headers']);
        check(strpos($headerLine, 'Content-Type: application/json') !== false
            && strpos($headerLine, 'cache-control: no-cache') !== false
            && stripos($headerLine, 'token') === false
            && stripos($headerLine, 'Authorization') === false
            && stripos($headerLine, 'nonce') === false,
            '无鉴权 HTTP 传输不能携带任何凭证 Header');
        foreach ([
            'https://api.e.qq.com/conv?cb=x&conv_id=97228665',
            'http://tracking.e.qq.com.evil.test/conv?cb=x&conv_id=97228665',
            'http://tracking.e.qq.com@evil.test/conv?cb=x&conv_id=97228665',
            'http://tracking.e.qq.com:8080/conv?cb=x&conv_id=97228665',
            'http://tracking.e.qq.com/other?cb=x&conv_id=97228665',
            'http://tracking.e.qq.com/conv?cb=x',
        ] as $invalid) {
            rejects(function () use ($service, $invalid) { $service->simulate($invalid); },
                'HTTP Callback 必须拒绝未授权的目标与缺少参数的 URL');
        }
        check(count($service->captures) === 1, '无效 URL 不得触发网络传输');
    };
    $tests['平台来源与荣耀隔离'] = function () {
        check(\App\Constants\AdConst::PUT_SOURCE_MAP['tx'] === 8 && \App\Constants\AdConst::PUT_SOURCE_MAP['honor'] === 7, '平台来源冲突');
        check(\App\Constants\MktConst::PLATFORM_SOURCE['tx'] === 8, '两端来源未对齐');
    };
    foreach ($tests as $name => $test) {
        $test();
        echo '通过：' . $name . PHP_EOL;
    }
    echo '共通过 ' . count($tests) . ' 组隔离测试。' . PHP_EOL;
}
