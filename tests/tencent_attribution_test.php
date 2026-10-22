<?php
/** 点击与归因流程测试：内存数据库/HTTP 替身，不执行真实请求或 DDL。 */
namespace TencentAttributionTest {
    class Query
    {
        private $table;
        private $conditions = [];
        private $orConditions = [];
        private $columns;
        private $order;
        public function __construct($table) { $this->table = $table; }
        public function select($columns) { $this->columns = $columns; return $this; }
        private function condition($key, $operator = null, $value = null)
        {
            if (is_callable($key)) {
                $nested = new self($this->table); $key($nested);
                return function ($row) use ($nested) { return $nested->matches($row); };
            }
            if ($value === null) { $value = $operator; $operator = '='; }
            return function ($row) use ($key, $operator, $value) {
                $actual = $row[$key] ?? null;
                return $operator === '>' ? $actual > $value : $actual == $value;
            };
        }
        public function where($key, $operator = null, $value = null) { $this->conditions[] = $this->condition($key, $operator, $value); return $this; }
        public function orWhere($key, $operator = null, $value = null) { $this->orConditions[] = $this->condition($key, $operator, $value); return $this; }
        public function orderBy($field, $direction) { $this->order = [$field, $direction]; return $this; }
        public function matches($row)
        {
            foreach ($this->conditions as $condition) { if (!$condition($row)) { return false; } }
            if ($this->orConditions) {
                foreach ($this->orConditions as $condition) { if ($condition($row)) { return true; } }
                return false;
            }
            return true;
        }
        public function first()
        {
            $rows = array_values(array_filter(\DB::$tables[$this->table] ?? [], function ($row) { return $this->matches($row); }));
            if ($this->order) {
                $field = $this->order[0]; $direction = $this->order[1];
                usort($rows, function ($a, $b) use ($field, $direction) { return $direction === 'desc' ? $b[$field] <=> $a[$field] : $a[$field] <=> $b[$field]; });
            }
            if (!$rows) { return null; }
            return $this->columns ? array_intersect_key($rows[0], array_flip($this->columns)) : $rows[0];
        }
        public function exists() { return $this->first() !== null; }
        public function leftJoin(...$args) { return $this; }
        public function value($field) { return 'test-vivo-token'; }
        public function insert($data)
        {
            $data += ['id' => count(\DB::$tables[$this->table] ?? []) + 1, 'device_id' => '', 'active_status' => 0, 'channel' => 0, 'vendor' => ''];
            \DB::$tables[$this->table][] = $data;
            return true;
        }
        public function update($data)
        {
            $count = 0;
            foreach (\DB::$tables[$this->table] as &$row) {
                if ($this->matches($row)) { $row = array_replace($row, $data); $count++; }
            }
            return $count;
        }
    }
    class Response
    {
        public $data;
        public $status;
        public function json($data, $status = 200) { $this->data = $data; $this->status = $status; return $this; }
    }
}
namespace Illuminate\Http {
    class Request
    {
        private $params;
        public function __construct($params) { $this->params = $params; }
        public function input() { return $this->params; }
    }
}
namespace Laravel\Lumen\Routing { class Controller {} }
namespace App\Services\Common {
    class App
    {
        public static $appSubType = ['tool_quick' => 2];
        public static function getAppByCode($code) { return $code === 'demo' ? ['package_name' => 'com.example.demo', 'sub_type' => 1] : []; }
        public static function tableAdTencent($code) { return 'app_' . $code . '_tx'; }
        public static function tableDevice($code) { return 'app_' . $code; }
        public static function tableAdBySource($code, $source) {
            $suffix = [1 => 'ad', 2 => 'ks', 3 => 'oppo', 4 => 'huawei', 5 => 'vivo', 6 => 'bd', 7 => 'honor', 8 => 'tx'][$source];
            return 'app_' . $code . '_' . $suffix;
        }
    }
    class Account
    {
        public static function info($id) { return ['active_type' => 2]; }
        public static function extra($id) { return ['sign_key' => 'test-sign-key', 'src_id' => 'test-src', 'akey' => 'test-akey']; }
    }
}
namespace App\Services\Helper {
    class Helper
    {
        public static $attribution = ['code' => 202];
        public static $calls = [];
        public static $responses = [];
        public static function request(...$args) {
            self::$calls[] = $args;
            return ['result' => json_encode(self::$responses ? array_shift(self::$responses) : self::$attribution)];
        }
    }
}
namespace App\Services\Helper\Encrypt {
    class Aes { public static function encrypt($value, $key) { return base64_encode('test:' . $value); } }
}
namespace App\Jobs {
    class DeviceActiveJob
    {
        public $data;
        public function __construct($data) { $this->data = $data; }
        public function onQueue($queue) { return $this; }
    }
}
namespace {
    class DB
    {
        public static $tables = [];
        private static $snapshot;
        public static function connection($name) { return new self(); }
        public static function table($table) { return new \TencentAttributionTest\Query($table); }
        public static function beginTransaction() { self::$snapshot = self::$tables; }
        public static function rollBack() { self::$tables = self::$snapshot; }
        public static function commit() { self::$snapshot = null; }
    }
    class Schema { public static function hasTable($table) { return array_key_exists($table, DB::$tables); } }
    class Log { public static function info(...$args) {} }
    $jobs = [];
    function dispatch($job) { global $jobs; $jobs[] = $job; }
    function response() { return new \TencentAttributionTest\Response(); }
    function ensure($condition, $message) { if (!$condition) { throw new \RuntimeException($message); } }

    $root = dirname(__DIR__);
    require $root . '/api_server/app/Constants/AdConst.php';
    require $root . '/api_server/app/Services/Mkt/TencentClick.php';
    require $root . '/api_server/app/Services/Mkt/GuiyinService.php';
    require $root . '/api_server/app/Services/Mkt/MktService.php';
    require $root . '/api_server/app/Http/Controllers/V1/AdController.php';

    $device = ['id' => 1, 'android_id' => 'device-android', 'oaid' => 'OAID-value', 'ad_time' => 0, 'source' => 0, 'channel' => 11, 'vendor' => 'vivo'];
    $click = ['advertiser_id' => '123', 'adgroup_id' => '9', 'click_id' => 'click-1', 'click_time' => (string)(time() - 60), 'oaid' => 'OAID-value'];
    $reset = function () use ($device) {
        global $jobs;
        DB::$tables = ['mkt_advertiser' => [['advertiser_id' => '123', 'platform' => 'tx', 'app_code' => 'demo', 'status' => 1]], 'app_demo_tx' => [], 'app_demo' => [$device], 'com_ad_error' => []];
        $jobs = [];
        \App\Services\Helper\Helper::$attribution = ['code' => 202];
    };
    $controller = new \App\Http\Controllers\V1\AdController();
    $reset();
    $request = new \Illuminate\Http\Request($click);
    ensure($controller->tx($request, 'demo')->data['ret'] === 0, '有效点击应接收成功');
    ensure($controller->tx($request, 'demo')->data['ret'] === 0 && count(DB::$tables['app_demo_tx']) === 1, '重复下发应保持一条点击');
    ensure(DB::$tables['app_demo_tx'][0]['aid'] === '9', '营销单元未映射为计划');
    echo '通过：点击入库与重复下发' . PHP_EOL;

    $attribution = \App\Services\Mkt\GuiyinService::handle('demo', $device);
    ensure($attribution['source'] === 8 && $attribution['ts'] === (int)$click['click_time'], '腾讯归因或点击时间遗漏');
    ensure(DB::$tables['app_demo'][0]['source'] === 8 && DB::$tables['app_demo'][0]['aid'] === '9', '外部归因处理中，本地命中未落库');
    ensure(DB::$tables['app_demo_tx'][0]['device_id'] === $device['android_id'] && count($jobs) === 1, '点击设备绑定或激活任务缺失');
    echo '通过：外部归因处理中仍落库腾讯命中并触发激活' . PHP_EOL;

    $reset(); $controller->tx($request, 'demo');
    \App\Services\Helper\Helper::$attribution = ['code' => 0, 'last_touch_time' => time() - 120, 'advertiser_id' => 'foreign'];
    $result = \App\Services\Mkt\GuiyinService::handle('demo', $device);
    ensure($result['source'] === 8, '较早的外部点击不应覆盖较新的腾讯点击');
    echo '通过：使用真实点击时间比较外部归因' . PHP_EOL;

    $reset(); $controller->tx($request, 'demo');
    DB::$tables['app_demo_ks'] = [array_replace(DB::$tables['app_demo_tx'][0], ['account_id' => 'ks-account', 'id' => 2, 'aid' => 'ks-plan'])];
    $result = \App\Services\Mkt\GuiyinService::handle('demo', $device);
    ensure($result['source'] === 2 && DB::$tables['app_demo'][0]['source'] === 2, '不得改变原有跨平台匹配顺序');
    echo '通过：保留已有平台匹配顺序' . PHP_EOL;

    $reset(); $controller->tx($request, 'demo');
    DB::$tables['app_demo_tx'][0]['ts'] = time() - 3 * 86400;
    ensure(\App\Services\Mkt\GuiyinService::handle('demo', $device) === [] && !$jobs, '过期点击不得归因');
    $reset(); $controller->tx($request, 'demo');
    DB::$tables['app_demo'][0]['ad_time'] = time() - 10;
    DB::$tables['app_demo'][0]['source'] = 1;
    ensure(\App\Services\Mkt\GuiyinService::handle('demo', $device) === [], '已归因设备不得再次认领');
    ensure(DB::$tables['app_demo'][0]['source'] === 1 && DB::$tables['app_demo_tx'][0]['device_id'] === '' && !$jobs, '认领失败时应回滚点击绑定且不派发激活');
    echo '通过：归因窗口及并发认领保护' . PHP_EOL;

    $reset(); unset(DB::$tables['app_demo_tx']);
    ensure($controller->tx($request, 'demo')->status === 503 && count(DB::$tables['com_ad_error']) === 1, '缺表不应返回成功');
    $reset(); DB::$tables['mkt_advertiser'][0]['platform'] = 'jl';
    ensure($controller->tx($request, 'demo')->status === 400 && !DB::$tables['app_demo_tx'], '其他平台账户不得写入腾讯点击');
    echo '通过：缺表及账户绑定校验' . PHP_EOL;

    foreach (range(1, 7) as $source) {
        foreach (['external-pending', 'older-external', 'lost-claim'] as $scenario) {
            $reset(); $controller->tx($request, 'demo');
            $table = \App\Services\Common\App::tableAdBySource('demo', $source);
            DB::$tables[$table] = [array_replace(DB::$tables['app_demo_tx'][0], ['id' => 20, 'account_id' => 'old-account', 'aid' => 'old-plan'])];
            if ($scenario === 'older-external') {
                \App\Services\Helper\Helper::$attribution = ['code' => 0, 'last_touch_time' => time() - 120, 'advertiser_id' => 'external-account'];
            } elseif ($scenario === 'lost-claim') {
                DB::$tables['app_demo'][0]['ad_time'] = time() - 10;
                DB::$tables['app_demo'][0]['source'] = 1;
            }
            $result = \App\Services\Mkt\GuiyinService::handle('demo', $device);
            if ($scenario === 'lost-claim') {
                ensure($result === [] && !$jobs && DB::$tables[$table][0]['device_id'] === '', '原平台认领失败应回滚且停止派发');
            } else {
                ensure($result['source'] === $source && DB::$tables['app_demo'][0]['source'] === $source && count($jobs) === 1, '共有缺陷修复应对原平台生效');
            }
        }
    }
    echo '通过：七个原平台外部归因处理中、本地点击较新及认领失败的共有修复' . PHP_EOL;

    // 原平台正常归因与回传快照，兼容性对照时使用同一组场景。
    $legacyAttributionSnapshots = [];
    foreach (range(1, 7) as $source) {
        $reset(); $controller->tx($request, 'demo');
        $table = \App\Services\Common\App::tableAdBySource('demo', $source);
        DB::$tables[$table] = [array_replace(DB::$tables['app_demo_tx'][0], ['id' => 20, 'account_id' => 'old-account', 'aid' => 'old-plan'])];
        \App\Services\Helper\Helper::$attribution = ['code' => 404];
        $result = \App\Services\Mkt\GuiyinService::handle('demo', $device);
        ensure($result['source'] === $source && DB::$tables['app_demo'][0]['source'] === $source, '原平台正常归因或优先级改变');
        ensure(DB::$tables[$table][0]['device_id'] === $device['android_id'] && count($jobs) === 1, '原平台绑定或激活派发改变');
        $legacyAttributionSnapshots[$source] = [
            'match' => [$result['id'], $result['account_id'], $result['aid'], $result['source']],
            'device' => [DB::$tables['app_demo'][0]['source'], DB::$tables['app_demo'][0]['aid'], DB::$tables['app_demo'][0]['ad_time'] > 0],
            'bound' => DB::$tables[$table][0]['device_id'], 'jobs' => count($jobs),
        ];
    }
    if (defined('LEGACY_ATTRIBUTION_SNAPSHOT_PATH')) {
        file_put_contents(LEGACY_ATTRIBUTION_SNAPSHOT_PATH, json_encode($legacyAttributionSnapshots, JSON_PRETTY_PRINT));
    }
    echo '通过：七个原平台正常归因、跨平台优先级及激活派发回归' . PHP_EOL;

    $callbackSnapshots = [];
    $eventTime = 1700000000;
    $params = ['app_code' => 'demo', 'account_id' => '123', 'callback_url' => 'https://example.test/callback?at={{ATYPE}}&av={{AVALUE}}', 'callback' => 'callback-id', 'oaid' => 'test-oaid', 'imei' => 'test-imei', 'aid' => 'plan-id', 'cid' => 'creative-id', 'request_id' => 'request-id', 'track_id' => 'track-id'];
    foreach (['jl', 'ks', 'op', 'hw', 'vi', 'bd', 'honor'] as $platform) {
        foreach (['activate', 'keep1', 'key_action', 'pay'] as $event) {
            $helper = \App\Services\Helper\Helper::class;
            $helper::$calls = [];
            $helper::$responses = [
                ['code' => 0, 'ret' => 0, 'resultCode' => 0, 'error_code' => 0, 'result' => 1],
                ['code' => 999, 'ret' => 999, 'resultCode' => 999, 'error_code' => 999],
                ['code' => 0, 'ret' => 0, 'resultCode' => 0, 'error_code' => 0, 'result' => 1],
            ];
            $callback = new \App\Services\Mkt\MktService(\App\Constants\AdConst::PUT_SOURCE_MAP[$platform]);
            ensure($callback->callback($event, $eventTime, $params) === true, '原平台成功回传判定改变：' . $platform . '/' . $event);
            ensure($callback->callback($event, $eventTime, $params) === false, '原平台失败回传判定改变');
            ensure($callback->callback($event, $eventTime, $params) === true, '原平台失败后重试判定改变');
            $requests = $helper::$calls;
            foreach ($requests as &$call) {
                // 仅归一化请求生成时的动态值；事件发生时间保持原值供对照。
                if ($platform === 'vi') {
                    $url = parse_url($call[0]); parse_str($url['query'], $query);
                    unset($query['timestamp'], $query['nonce']);
                    $call[0] = $url['scheme'] . '://' . $url['host'] . $url['path'] . '?' . http_build_query($query);
                }
                if ($platform === 'hw') {
                    $body = json_decode($call[2], true); unset($body['timestamp']); $call[2] = $body;
                    unset($call[3]['Authorization']);
                }
                if ($platform === 'op') { unset($call[3]['signature'], $call[3]['timestamp']); }
            }
            unset($call);
            $callbackSnapshots[$platform][$event] = $requests;
        }
    }
    if (defined('LEGACY_CALLBACK_SNAPSHOT_PATH')) {
        file_put_contents(LEGACY_CALLBACK_SNAPSHOT_PATH, json_encode($callbackSnapshots, JSON_PRETTY_PRINT));
    }
    echo '通过：七个原平台激活、留存、关键行为、付费回传成功失败及重试判定' . PHP_EOL;
    echo '共通过 9 组点击归因与回传流程测试。' . PHP_EOL;
}
