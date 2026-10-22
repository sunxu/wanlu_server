<?php
/** 运行真实 MktService，内存模型仅替代数据库与外部网络。 */
namespace HonorPersistence {
    class Query {
        private $class;
        private $filters = [];
        public function __construct($class) { $this->class = $class; }
        public function where($key, $operator, $value = null) {
            if (func_num_args() === 2) { $value = $operator; $operator = '='; }
            $this->filters[] = [$key, $operator, $value]; return $this;
        }
        public function first() {
            foreach ($this->class::$rows as $row) {
                $match = true;
                foreach ($this->filters as [$key, $op, $value]) {
                    $equal = (string)$row->$key === (string)$value;
                    if ($op === '!=' ? $equal : !$equal) { $match = false; }
                }
                if ($match) { return $row; }
            }
            return null;
        }
        public function exists() { return $this->first() !== null; }
        public function select($fields) { return $this; }
        public function get() {
            $rows = [];
            foreach ($this->class::$rows as $row) {
                $match = true;
                foreach ($this->filters as [$key, $op, $value]) {
                    $equal = (string)$row->$key === (string)$value;
                    if ($op === '!=' ? $equal : !$equal) { $match = false; }
                }
                if ($match) { $rows[] = $row->toArray(); }
            }
            return new class($rows) {
                private $rows;
                public function __construct($rows) { $this->rows = $rows; }
                public function toArray() { return $this->rows; }
            };
        }
        public function pluck($value, $key) {
            return new class { public function toArray() { return []; } };
        }
    }
    class Model {
        public static $classes = [];
        public $exists = false;
        private $data = [];
        public function __construct($data = []) { $this->fill($data); }
        public function __get($key) {
            if (array_key_exists($key, $this->data)) { return $this->data[$key]; }
            $relations = ['app' => ['MktApp', 'app_id'], 'user' => ['MktUser', 'user_id'],
                'account' => ['MktAccount', 'account_id'], 'advertiser' => ['MktAdvertiser', 'advertiser_id']];
            if (isset($relations[$key])) { [$class, $id] = $relations[$key]; $class = '\\App\\Model\\Mkt\\' . $class; return $class::find($this->data[$id] ?? ''); }
            return null;
        }
        public function __isset($key) { return $this->__get($key) !== null; }
        public function __set($key, $value) { $this->data[$key] = $value; }
        public function toArray() { return $this->data; }
        public function fill($data) { foreach ($data as $k => $v) { $this->data[$k] = $v; } return $this; }
        public static function find($id) { return static::$rows[(string)$id] ?? null; }
        public static function query() { return new Query(static::class); }
        public function save() {
            $key = static::$key;
            if ($key === 'id' && !isset($this->data[$key])) { $this->data[$key] = count(static::$rows) + 1; }
            $this->exists = true;
            static::$rows[(string)$this->data[$key]] = $this;
            return true;
        }
    }
}
namespace App\Model {
    class App extends \HonorPersistence\Model { public static $rows = []; public static $key = 'app_code'; }
}
namespace App\Model\Mkt {
    class MktApp extends \HonorPersistence\Model { public static $rows = []; public static $key = 'app_id'; }
    class MktUser extends \HonorPersistence\Model { public static $rows = []; public static $key = 'user_id'; }
    class MktAccount extends \HonorPersistence\Model { public static $rows = []; public static $key = 'account_id'; }
    class MktAdvertiser extends \HonorPersistence\Model { public static $rows = []; public static $key = 'advertiser_id'; }
    class MktProject extends \HonorPersistence\Model { public static $rows = []; public static $key = 'project_id'; }
    class MktAd extends \HonorPersistence\Model { public static $rows = []; public static $key = 'ad_id'; }
    class MktPlan extends \HonorPersistence\Model { public static $rows = []; public static $key = 'plan_id'; }
    class ReportDailyAdvertiser extends \HonorPersistence\Model { public static $rows = []; public static $key = 'id'; }
    class ReportDailyProject extends \HonorPersistence\Model { public static $rows = []; public static $key = 'id'; }
    class ReportDailyAd extends \HonorPersistence\Model { public static $rows = []; public static $key = 'id'; }
}
namespace App\Utils { class Log { public static function http($level, $title, $data) {} } }
namespace {
    use App\Model\Mkt\MktApp;
    use App\Model\Mkt\MktUser;
    use App\Model\Mkt\MktAccount;
    use App\Model\Mkt\MktAdvertiser;
    use App\Model\Mkt\MktProject;
    use App\Model\Mkt\MktAd;
    use App\Model\Mkt\ReportDailyAdvertiser;
    use App\Model\Mkt\ReportDailyProject;
    use App\Model\Mkt\ReportDailyAd;

    require dirname(__DIR__) . '/admin_server/app/Constants/MktConst.php';
    require dirname(__DIR__) . '/admin_server/app/Service/Mkt/MktService.php';
    function ensure($condition, $message) { if (!$condition) { throw new \RuntimeException($message); } }
    function blocked($call) { try { $call(); } catch (\Exception $e) { return; } throw new \RuntimeException('应拒绝冲突或无效请求'); }
    function data_get($data, $key, $default = null) { return is_object($data) ? ($data->$key ?? $default) : ($data[$key] ?? $default); }
    foreach (get_declared_classes() as $class) {
        if (strpos($class, 'App\\Model\\Mkt\\') === 0) { \HonorPersistence\Model::$classes[] = $class; }
    }
    class Adapter {
        public $calls = [];
        public $projectPages = [];
        public $adPages = [];
        public $reports = [];
        public $failRefresh = false;
        public function setApp($id, $secret) { $this->calls[] = ['app', $id, $secret]; return $this; }
        public function setToken($token) { return $this; }
        public function authRefreshToken($token) {
            $this->calls[] = ['refresh', $token];
            if ($this->failRefresh) { throw new \RuntimeException('模拟凭证刷新失败'); }
            return ['app_id' => 'client', 'access_token' => 'Bearer refreshed', 'access_time' => 999999];
        }
        public function authAccountGet() { return []; }
        public function advertiserGet($id) { return [['advertiser_id' => $id, 'account_id' => $id, 'advertiser_name' => $id]]; }
        public function projectGet($id, $page, $size, $ids) { $this->calls[] = ['project', $page]; return $this->projectPages[$page] ?? ['list' => [], 'page_info' => ['total_page' => 0]]; }
        public function adGet($id, $page, $size, $ids) { $this->calls[] = ['ad', $page, $ids]; return $this->adPages[$page] ?? ['list' => [], 'page_info' => ['total_page' => 0]]; }
        public function reportAdvertiserGet($id, $date, $grain, $unused = null) { return $this->reports[$grain] ?? []; }
        public function reportProjectGet($id, $date, $ids, $grain, $page, $size, $unused = null) { return ['list' => $this->reports['project'] ?? [], 'page_info' => ['total_page' => 1]]; }
        public function reportAdGet($id, $date, $ids, $grain, $page, $size, $unused = null) { return ['list' => $this->reports['ad'] ?? [], 'page_info' => ['total_page' => 1]]; }

    }
    class Service extends \App\Service\Mkt\MktService {
        public $adapter;
        public function getMkt(string $platform) { return $this->adapter; }
        public function project($data) { return $this->saveProject($data); }
        public function ad($data) { return $this->saveAd($data); }
        public function advertiser($data) { return $this->saveAdvertiser($data); }
    }
    $setup = function () {
        foreach (\HonorPersistence\Model::$classes as $class) { $class::$rows = []; }
        (new MktApp(['app_id' => 'client', 'platform' => 'honor', 'app_secret' => 'secret']))->save();
        // 使用环境既有配置，用户ID不要求等于客户端ID。
        (new MktUser(['user_id' => 'configured-user', 'app_id' => 'client', 'access_token' => 'Bearer old', 'refresh_token' => '', 'refresh_time' => 0]))->save();
        (new MktAccount(['account_id' => '123', 'user_id' => 'configured-user', 'is_valid' => 1]))->save();
        (new MktAdvertiser(['advertiser_id' => '123', 'account_id' => '123', 'app_code' => 'demo', 'platform' => 'honor', 'advertiser_name' => '123', 'status' => 1, 'extra' => [], 'notify_discount' => 30]))->save();
        $s = new Service(); $s->adapter = new Adapter(); return $s;
    };
    $s = $setup();
    $before = MktAdvertiser::find('123')->toArray();
    $s->authToken('configured-user');
    ensure(MktUser::find('configured-user')->access_token === 'Bearer refreshed' && MktUser::find('configured-user')->refresh_token === '', '已有配置无刷新令牌时刷新失败');
    ensure(in_array(['refresh', ''], $s->adapter->calls, true), '必须通过原有authToken流程调用适配器');
    $s->advertiserList('123'); $s->accountList('configured-user');
    ensure(MktAdvertiser::find('123')->toArray() === $before && count(MktUser::$rows) === 1 && count(MktAccount::$rows) === 1, '刷新及查询不应重建授权关系或重置业务配置');
    $s->adapter->failRefresh = true;
    blocked(function () use ($s) { $s->authToken('configured-user'); });
    ensure(MktUser::find('configured-user')->access_token === 'Bearer refreshed', '刷新失败不能清空已有令牌');
    echo '通过：已有配置、任意授权用户ID、凭证刷新及失败保留' . PHP_EOL;

    $s = $setup();
    $s->adapter->projectPages = [1 => ['list' => [['project_id' => '8', 'advertiser_id' => '123', 'project_name' => '项目']], 'page_info' => ['total_page' => 2]],
        2 => ['list' => [['project_id' => '18', 'advertiser_id' => '123', 'project_name' => '项目2']], 'page_info' => ['total_page' => 2]]];
    $s->adapter->adPages = [1 => ['list' => [['ad_id' => '9', 'project_id' => '8', 'advertiser_id' => '123', 'ad_name' => '广告', 'put_status' => 1]], 'page_info' => ['total_page' => 1]]];
    $s->projectList('123'); $s->adList('123');
    ensure(count(MktProject::$rows) === 2 && MktAd::find('9')->app_code === 'demo', '未接入现有项目／广告分页同步');
    $calls = $s->adapter->calls;
    $s->planList('123'); $s->reportDailyPlan('123', '2026-10-21'); $s->reportHourlyPlan('123', '2026-10-21');
    ensure($s->adapter->calls === $calls, '荣耀必须跳过计划链路');
    $base = ['date' => '2026-10-21', 'advertiser_id' => '123', 'cost' => 2];
    $s->adapter->reports = ['daily' => [$base], 'hourly' => [array_merge($base, ['hour' => '00'])],
        'project' => [array_merge($base, ['project_id' => '8']), array_merge($base, ['project_id' => '18', 'cost' => 0])],
        'ad' => [array_merge($base, ['ad_id' => '9', 'project_id' => '8'])]];
    foreach (['reportDailyAdvertiser', 'reportHourlyAdvertiser', 'reportDailyProject', 'reportDailyAd'] as $method) { $s->$method('123', '2026-10-21'); }
    ensure(count(ReportDailyAdvertiser::$rows) === 1 && isset(ReportDailyAdvertiser::find(1)->hour_info['00']), '广告主日报或小时报表未保存');
    ensure(count(ReportDailyProject::$rows) === 1 && count(ReportDailyAd::$rows) === 1, '报表应继续只入库正消耗');
    $s->reportDailyAd('123', '2026-10-21');
    ensure(count(ReportDailyAd::$rows) === 1, '重复同步应更新已有日报');
    echo '通过：项目／广告多页同步、计划隔离、日报小时报表及正消耗入库' . PHP_EOL;

    (new MktAdvertiser(['advertiser_id' => 'foreign', 'platform' => 'jl', 'account_id' => 'foreign']))->save();
    blocked(function () use ($s) { $s->project(['project_id' => '8', 'advertiser_id' => 'foreign']); });
    blocked(function () use ($s) { $s->ad(['ad_id' => '9', 'project_id' => '8', 'advertiser_id' => 'foreign']); });
    (new MktProject(['project_id' => '80', 'advertiser_id' => 'foreign']))->save();
    blocked(function () use ($s) { $s->project(['project_id' => '80', 'advertiser_id' => '123']); });
    blocked(function () use ($s) { $s->ad(['ad_id' => '90', 'project_id' => '80', 'advertiser_id' => '123']); });
    blocked(function () use ($s) { $s->advertiser(['advertiser_id' => '123', 'account_id' => 'other', 'platform' => 'honor']); });
    foreach ([ReportDailyAdvertiser::class => 'reportDailyAdvertiser', ReportDailyProject::class => 'reportDailyProject', ReportDailyAd::class => 'reportDailyAd'] as $class => $method) {
        $r = $class::find(1); $r->platform = 'jl'; $r->save();
        blocked(function () use ($s, $method) { $s->$method('123', '2026-10-21'); });
        ensure($class::find(1)->platform === 'jl', '跨平台报表被覆盖');
        $r->platform = 'honor'; $r->save();
    }
    $savedReport = $s->adapter->reports['project'];
    $s->adapter->reports['project'] = [array_merge($base, ['project_id' => '80'])];
    blocked(function () use ($s) { $s->reportDailyProject('123', '2026-10-21'); });
    ensure(count(ReportDailyProject::$rows) === 1, '即使还没有同日旧报表，也不能写入其他平台项目ID');
    $s->adapter->reports['project'] = $savedReport;
    echo '通过：项目／广告归属、广告主绑定和日报跨平台冲突保护' . PHP_EOL;

    $calls = count($s->adapter->calls);
    blocked(function () use ($s) { $s->adUpdateStatus('9', 'disable'); });
    blocked(function () use ($s) { $s->adBatchUpdateStatus('123', [['ad_id' => '9', 'status' => 'enable']]); });
    ensure(count($s->adapter->calls) === $calls && MktAd::find('9')->put_status === 1, '不能额外放开荣耀公共启停及自动规则');
    echo '通过：公共业务层未额外开放荣耀启停' . PHP_EOL;
    echo '共通过 4 组荣耀刷新与同步流程测试。' . PHP_EOL;
}
