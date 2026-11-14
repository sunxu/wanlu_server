<?php
/** 持久化边界测试：使用内存模型替身，不连接数据库。 */
namespace TencentTest {
    class Query
    {
        private $rows;
        public function __construct(array $rows) { $this->rows = $rows; }
        public function select($fields) { return $this; }
        public function where($field, $operator, $value = null) {
            if (func_num_args() === 2) { $value = $operator; $operator = '='; }
            $this->rows = array_filter($this->rows, function ($row) use ($field, $operator, $value) {
                $equal = ($row->$field ?? null) == $value;
                return $operator === '!=' ? !$equal : $equal;
            });
            return $this;
        }
        public function orderBy($field, $direction) { return $this; }
        public function get() { return $this; }
        public function first() { return $this->all()[0] ?? null; }
        public function all() { return array_values($this->rows); }
        public function toArray() { return array_map(function ($row) { return (array)$row; }, $this->all()); }
    }
    class Model
    {
        public static $rows = [];
        public $writes = 0;
        public function __construct(array $attributes = []) { foreach ($attributes as $key => $value) { $this->$key = $value; } }
        public static function find($id) { return static::$rows[(string)$id] ?? null; }
        public static function query() { return new Query(static::$rows); }
        public static function select($fields) { return static::query()->select($fields); }
        public static function where($field, $value) { return static::query()->where($field, $value); }
        public function fill(array $attributes) { foreach ($attributes as $key => $value) { $this->$key = $value; } return $this; }
        public function save() { $this->writes++; return true; }
        public function getTable() { return 'mkt_advertiser'; }
    }
}
namespace App\Model\Mkt {
    class MktApp extends \TencentTest\Model { public static $rows = []; }
    class MktUser extends \TencentTest\Model { public static $rows = []; }
    class MktAccount extends \TencentTest\Model { public static $rows = []; }
    class MktAdvertiser extends \TencentTest\Model { public static $rows = []; }
    class MktPlan extends \TencentTest\Model { public static $rows = []; }
    class MktAd extends \TencentTest\Model { public static $rows = []; }
    class MktProject extends \TencentTest\Model {
        public static $rows = [];
        public static $findCalls = 0;
        public static function find($id) { self::$findCalls++; return parent::find($id); }
    }
    class ReportDailyAdvertiser extends \TencentTest\Model { public static $rows = []; }
    class ReportDailyPlan extends \TencentTest\Model { public static $rows = []; }
}
namespace App\Controller {
    class AbstractController
    {
        public $request;
        public function responseSuccess($data = []) { return $data; }
        public function responseRecord($records) { return ['records' => $records]; }
    }
    class AbstractAction extends AbstractController {
        public function validated(array $params) { return $params; }
    }
}
namespace App\Utils {
    trait ValidateTrait {}
}
namespace Hyperf\DbConnection {
    class Db
    {
        public static $sql;
        public static function raw($sql) { return $sql; }
        public static function select($sql) { self::$sql = $sql; throw new \RuntimeException('已捕获报表查询'); }
    }
}
namespace Hyperf\HttpServer\Contract {
    interface RequestInterface {}
}
namespace Hyperf\Constants {
    class AbstractConstants {}
}
namespace {
    $root = dirname(__DIR__);
    require $root . '/admin_server/app/Constants/MktConst.php';
    require $root . '/admin_server/app/Service/Mkt/TxPrivateCredentialId.php';
    require $root . '/admin_server/app/Service/Mkt/MktService.php';
    require $root . '/admin_server/app/Controller/Mkt/CommonController.php';
    require $root . '/admin_server/app/Controller/Launch/LadController.php';
    require $root . '/admin_server/app/Constants/RedisConst.php';
    require $root . '/admin_server/app/Controller/Put/Advertiser/SaveAction.php';

    function data_get($data, $key, $default = null) { return $data[$key] ?? $default; }
    function redis_client($pool) { return new class { public function del($key) {} }; }
    class FilterRequest implements \Hyperf\HttpServer\Contract\RequestInterface
    {
        public $params;
        public function __construct(array $params) { $this->params = $params; }
        public function all() { return $this->params; }
        public function input($key, $default = null) { return $this->params[$key] ?? $default; }
        public function query($key, $default = null) { return $this->input($key, $default); }
    }

    use App\Model\Mkt\MktApp;
    use App\Model\Mkt\MktUser;
    use App\Model\Mkt\MktAccount;
    use App\Model\Mkt\MktAdvertiser;
    use App\Model\Mkt\MktPlan;

    class PersistenceService extends \App\Service\Mkt\MktService
    {
        public $adapterCalls = 0;
        public $syncAdapter;
        public function user(array $data) { return $this->saveUser($data); }
        public function account(array $data) { return $this->saveAccount($data); }
        public function advertiser(array $data) { return $this->saveAdvertiser($data); }
        public function plan(array $data) { return $this->savePlan($data); }
        public function ad(array $data) { return $this->saveAd($data); }
        public function getMkt(string $platform) {
            $this->adapterCalls++;
            if ($this->syncAdapter) { return $this->syncAdapter; }
            throw new \RuntimeException('不应调用平台适配器');
        }
    }
    function blocked($call, $expected) {
        try { $call(); } catch (\Exception $e) {
            if (strpos($e->getMessage(), $expected) !== false) { return; }
            throw $e;
        }
        throw new \RuntimeException('预期拒绝：' . $expected);
    }
    function ensure($condition, $message) { if (!$condition) { throw new \RuntimeException($message); } }

    $service = new PersistenceService();
    MktApp::$rows['other'] = new MktApp(['platform' => 'jl']);
    MktUser::$rows['u'] = new MktUser(['app_id' => '456', 'app' => (object)['platform' => 'tx'], 'refresh_token' => 'old-refresh', 'refresh_time' => 1234]);
    blocked(function () use ($service) { $service->user(['user_id' => 'u', 'app_id' => 'other']); }, '用户ID');
    ensure(MktUser::$rows['u']->writes === 0 && MktUser::$rows['u']->app_id === '456', '用户冲突不应修改原数据');
    $service->user(['user_id' => 'u', 'app_id' => '456', 'access_token' => 'new-access']);
    ensure(MktUser::$rows['u']->refresh_token === 'old-refresh' && MktUser::$rows['u']->refresh_time === 1234, '更新 access_token 不应清空旧刷新信息');
    echo '通过：授权用户冲突保护及令牌局部更新' . PHP_EOL;

    MktUser::$rows['foreign'] = new MktUser(['app' => (object)['platform' => 'jl']]);
    MktAccount::$rows['123'] = new MktAccount(['user_id' => 'u', 'user' => MktUser::$rows['u']]);
    blocked(function () use ($service) { $service->account(['account_id' => '123', 'user_id' => 'foreign']); }, '账户ID');
    ensure(MktAccount::$rows['123']->writes === 0 && MktAccount::$rows['123']->user_id === 'u', '其他平台不应覆盖腾讯账户');
    MktAccount::$rows['123'] = new MktAccount(['user_id' => 'foreign', 'user' => MktUser::$rows['foreign']]);
    blocked(function () use ($service) { $service->account(['account_id' => '123', 'user_id' => 'u']); }, '账户ID');
    ensure(MktAccount::$rows['123']->writes === 0, '腾讯不应覆盖其他平台账户');
    echo '通过：跨平台账户双向冲突保护' . PHP_EOL;

    MktAdvertiser::$rows['123'] = new MktAdvertiser(['platform' => 'jl', 'account_id' => 'old', 'app_code' => 'demo', 'advertiser_name' => '相同名称']);
    blocked(function () use ($service) { $service->advertiser(['platform' => 'tx', 'advertiser_id' => '123', 'account_id' => '123', 'advertiser_name' => '相同名称']); }, '广告主ID');
    ensure(MktAdvertiser::$rows['123']->writes === 0, '已有绑定且同名时也必须先校验平台');
    MktAdvertiser::$rows['123']->platform = 'tx';
    blocked(function () use ($service) { $service->advertiser(['platform' => 'jl', 'advertiser_id' => '123', 'account_id' => 'other', 'advertiser_name' => '新名称']); }, '广告主ID');
    blocked(function () use ($service) { $service->advertiser(['platform' => 'tx', 'advertiser_id' => '123', 'account_id' => 'changed', 'advertiser_name' => '相同名称']); }, '账户绑定');
    echo '通过：广告主平台及绑定关系保护' . PHP_EOL;

    MktPlan::$rows['9'] = new MktPlan(['advertiser_id' => 'foreign', 'advertiser' => (object)['platform' => 'jl']]);
    blocked(function () use ($service) { $service->plan(['plan_id' => '9', 'advertiser_id' => '123']); }, '计划ID');
    ensure(MktPlan::$rows['9']->writes === 0 && MktPlan::$rows['9']->advertiser_id === 'foreign', '腾讯计划不得覆盖已有计划');
    MktPlan::$rows['9']->advertiser = (object)['platform' => 'tx'];
    blocked(function () use ($service) { $service->planUpdateStatus('9', 'disable'); }, '仅支持查询');
    blocked(function () use ($service) { $service->planUpdateBudget('9', 100); }, '仅支持查询');
    blocked(function () use ($service) { $service->planEmptyDayScheduleTime('9'); }, '仅支持查询');
    ensure($service->adapterCalls === 0, '公共修改入口应在加载适配器前拒绝腾讯');
    echo '通过：计划冲突及公共修改入口保护' . PHP_EOL;
    // 部分媒体的广告同步不提供 project_id；无需查询项目或使用缺失键。
    \App\Model\Mkt\MktAd::$rows = ['ad-no-project' => new \App\Model\Mkt\MktAd(['advertiser_id' => '8'])];
    \App\Model\Mkt\MktProject::$rows = [
        'project-foreign' => new \App\Model\Mkt\MktProject(['advertiser_id' => 'honor-adv'])
    ];
    MktAdvertiser::$rows['honor-adv'] = new MktAdvertiser(['advertiser_id' => 'honor-adv', 'platform' => 'honor']);
    \App\Model\Mkt\MktProject::$findCalls = 0;
    ensure($service->ad(['ad_id' => 'ad-no-project', 'advertiser_id' => '8']) === true, '缺少 project_id 时应允许保存广告');
    ensure(\App\Model\Mkt\MktProject::$findCalls === 0, '缺少 project_id 时不得查询项目');
    ensure($service->ad(['ad_id' => 'ad-no-project', 'advertiser_id' => '8', 'project_id' => null]) === true, 'null project_id 不应触发校验');
    ensure(\App\Model\Mkt\MktProject::$findCalls === 0, 'null project_id 不得查询项目');
    blocked(function () use ($service) {
        $service->ad(['ad_id' => 'ad-no-project', 'advertiser_id' => '8', 'project_id' => 'project-foreign']);
    }, '荣耀项目');
    ensure(\App\Model\Mkt\MktProject::$findCalls === 1, '提供项目ID时必须验证项目归属');
    echo '通过：广告 project_id 可选且荣耀归属仍受保护' . PHP_EOL;

    MktAdvertiser::$rows = [
        '1' => new MktAdvertiser(['advertiser_id' => '1', 'app_code' => 'demo', 'platform' => 'jl']),
        '2' => new MktAdvertiser(['advertiser_id' => '2', 'app_code' => 'demo', 'platform' => 'ks']),
        '8' => new MktAdvertiser(['advertiser_id' => '8', 'app_code' => 'demo', 'platform' => 'tx']),
        '9' => new MktAdvertiser(['advertiser_id' => '9', 'app_code' => 'other', 'platform' => 'tx']),
    ];
    MktPlan::$rows = ['tx-plan' => new MktPlan(['plan_id' => 'tx-plan', 'advertiser_id' => '8', 'app_code' => 'demo'])];
    $common = new \App\Controller\Mkt\CommonController();
    $common->request = new FilterRequest(['app_code' => 'demo', 'advertiser_id' => '8', 'for_rules' => 1]);
    ensure(array_column($common->getAdvertiser()['list'], 'platform') === ['jl', 'ks', 'tx'], '公共广告主列表必须包含腾讯');
    ensure(count($common->getAdvertisers('demo')['records']) === 3, '公共接口不应按规则用途隐藏平台');
    ensure($common->getAdvertiserPlan()['list'][0]['plan_id'] === 'tx-plan', '公共计划查询必须返回腾讯计划');
    echo '通过：公共广告主与计划查询统一返回各平台' . PHP_EOL;

    $legacy = new \App\Controller\Launch\LadController();
    foreach ([1 => 'jl', 2 => 'ks', 8 => 'tx'] as $source => $platform) {
        $request = new FilterRequest(['appcode' => 'demo', 'plat' => (string)$source]);
        $list = $legacy->getAdvertiser($request)['list'];
        ensure(count($list) === 1 && $list[0]['platform'] === $platform, '旧版广告主筛选应只返回所选平台');
        foreach (['ad' => 'a', 'plan' => 'a', 'adNew' => 'a', 'planNew' => 'b'] as $method => $alias) {
            blocked(function () use ($legacy, $method, $request) { $legacy->$method($request); }, '已捕获报表查询');
            ensure(strpos(\Hyperf\DbConnection\Db::$sql, "and {$alias}.platform = '{$platform}'") !== false, '旧版报表应按平台构建查询：' . $method);
        }
    }
    echo '通过：已有数字来源筛选及四个旧版报表查询兼容腾讯' . PHP_EOL;
    foreach (['tx', 'ks'] as $platform) {
        $extra = ['user_action_set_id' => '123', 'other' => ['keep' => true]];
        $advertiser = new MktAdvertiser([
            'advertiser_id' => '8', 'app_code' => 'demo', 'platform' => $platform,
            'advertiser_name' => '测试', 'extra' => $extra,
            'account' => (object)['user' => (object)['access_token' => 'test-token']],
        ]);
        MktAdvertiser::$rows = ['8' => $advertiser];
        $report = new \App\Model\Mkt\ReportDailyAdvertiser(['date' => '2026-10-21', 'advertiser_id' => '8', 'platform' => $platform, 'exists' => true]);
        \App\Model\Mkt\ReportDailyAdvertiser::$rows = [$report];
        $adapter = new class {
            public $cost = 0;
            public $extra;
            public $planArgs;
            public function setToken($token) { return $this; }
            public function setAppInfo($extra) { $this->extra = $extra; return $this; }
            public function reportAdvertiserGet($id, $date, $granularity, $search) { return [['advertiser_id' => $id, 'date' => $date, 'hour' => '01', 'cost' => $this->cost]]; }
            public function planGet(...$args) { $this->planArgs = $args; return ['list' => [['advertiser_id' => $args[0], 'plan_id' => 'tx-plan']], 'page_info' => ['total_page' => 1]]; }
        };
        $service->syncAdapter = $adapter;
        $service->reportDailyAdvertiser('8', '2026-10-21');
        $service->reportHourlyAdvertiser('8', '2026-10-21');
        ensure($report->writes === 0, '各平台应使用相同的零消耗入库规则');
        $adapter->cost = 10;
        $service->reportDailyAdvertiser('8', '2026-10-21');
        $service->reportHourlyAdvertiser('8', '2026-10-21');
        ensure($report->writes === 2, '各平台应共用报表更新流程');
        ensure($adapter->extra === ($platform === 'tx' ? $extra : null), '腾讯报表配置不得覆盖原平台适配器的应用配置');
        $service->planList('8');
        ensure(count($adapter->planArgs) === 5 && $adapter->planArgs[1] === 1, '公共同步不应增加平台游标参数');
        $advertiser->extra['removed'] = 'old';
        $save = new \App\Controller\Put\Advertiser\SaveAction();
        $params = array_fill_keys(['launch_type', 'action_type', 'action_revenue', 'active_type', 'keep_type', 'notify_fill'], 0);
        $params += ['advertiser_id' => '8', 'advertiser_name' => '测试', 'extra' => json_encode($extra)];
        $save->request = new FilterRequest($params);
        $save->handle();
        ensure($advertiser->extra === $extra, '完整 JSON 保存应保留其他配置且允许删除旧键');
    }
    echo '通过：各平台共用计划同步、报表入库及完整扩展配置保存' . PHP_EOL;

    // 原平台回归：同一组调用与结果可供接入前版本作对照。
    $legacySnapshots = [];
    foreach (['jl', 'ks', 'hw', 'op', 'vi', 'bd'] as $platform) {
        $adapter = new class {
            public $calls = [];
            public $appInfo = ['auth_url' => '已有应用配置'];
            public $tokenData = ['app_id' => 'legacy-app', 'access_token' => 'new-access', 'access_time' => 2000, 'refresh_token' => 'new-refresh', 'refresh_time' => 3000];
            public $fail = false;
            public $empty = false;
            public $failSync = false;
            public function setApp(...$args) { $this->calls[] = ['setApp', $args]; return $this; }
            public function setToken(...$args) { $this->calls[] = ['setToken', $args]; return $this; }
            public function setAppInfo($info) { $this->calls[] = ['setAppInfo', $info]; $this->appInfo = $info; return $this; }
            public function authRefreshToken($token) {
                $this->calls[] = ['refresh', $token];
                if ($this->fail) { throw new \RuntimeException('刷新失败'); }
                return $this->tokenData;
            }
            public function planGet(...$args) {
                $this->calls[] = ['plan', $args];
                if ($this->failSync) { throw new \RuntimeException('同步失败'); }
                if ($this->empty) { return ['list' => [], 'page_info' => ['total_page' => 0]]; }
                return ['list' => [['advertiser_id' => '8', 'plan_id' => 'plan-' . $args[1]]], 'page_info' => ['total_page' => 2]];
            }
            public function reportAdvertiserGet(...$args) {
                $this->calls[] = ['advertiser-report', $args];
                if ($this->failSync) { throw new \RuntimeException('同步失败'); }
                if ($this->empty) { return []; }
                return [['advertiser_id' => '8', 'date' => $args[1], 'hour' => '01', 'cost' => 10, 'active' => 2]];
            }
            public function reportPlanGet(...$args) {
                $this->calls[] = ['plan-report', $args];
                if ($this->failSync) { throw new \RuntimeException('同步失败'); }
                if ($this->empty) { return ['list' => [], 'page_info' => ['total_page' => 0]]; }
                return [
                    'list' => [
                        ['advertiser_id' => '8', 'plan_id' => 'plan-' . $args[4], 'date' => $args[1], 'cost' => 10],
                        ['advertiser_id' => '8', 'plan_id' => 'zero-cost', 'date' => $args[1], 'cost' => 0],
                    ],
                    'lastId' => 'last-' . $args[4], 'page_info' => ['total_page' => 2],
                ];
            }
        };
        $app = new MktApp(['platform' => $platform, 'app_id' => 'legacy-app', 'app_secret' => 'legacy-secret']);
        $user = new MktUser(['user_id' => 'legacy-user', 'app_id' => 'legacy-app', 'app' => $app, 'refresh_token' => 'old-refresh', 'refresh_time' => 1000]);
        MktApp::$rows = ['legacy-app' => $app];
        MktUser::$rows = ['legacy-user' => $user];
        $legacyService = new PersistenceService();
        $legacyService->syncAdapter = $adapter;
        $legacyService->authToken('legacy-user');
        ensure($user->refresh_token === 'new-refresh' && $user->refresh_time === 3000, '原平台应继续保存续期返回的完整令牌');
        $adapter->tokenData = ['app_id' => 'legacy-app', 'access_token' => 'partial-access', 'access_time' => 4000];
        $legacyService->authToken('legacy-user');
        ensure($user->refresh_token === 'new-refresh' && $user->refresh_time === 3000, '缺省刷新字段应保持原有 fill 语义');
        $adapter->fail = true;
        blocked(function () use ($legacyService) { $legacyService->authToken('legacy-user'); }, '刷新失败');
        ensure($user->writes === 2 && $user->access_token === 'partial-access', '续期失败不能修改已有令牌');
        $advertiser = new MktAdvertiser([
            'advertiser_id' => '8', 'app_code' => 'demo', 'platform' => $platform, 'advertiser_name' => '原平台广告主',
            'extra' => ['search' => true, 'sign_id' => 'sign-id', 'sign_key' => 'sign-key'],
            'account' => (object)['user' => $user],
        ]);
        MktAdvertiser::$rows = ['8' => $advertiser];
        MktPlan::$rows = [];
        \App\Model\Mkt\ReportDailyPlan::$rows = [];
        foreach ([1, 2] as $page) {
            MktPlan::$rows['plan-' . $page] = new MktPlan(['plan_id' => 'plan-' . $page, 'advertiser_id' => '8']);
            \App\Model\Mkt\ReportDailyPlan::$rows[] = new \App\Model\Mkt\ReportDailyPlan(['plan_id' => 'plan-' . $page, 'advertiser_id' => '8', 'date' => '2026-10-21', 'platform' => $platform, 'exists' => true]);
        }
        $report = new \App\Model\Mkt\ReportDailyAdvertiser(['date' => '2026-10-21', 'advertiser_id' => '8', 'platform' => $platform, 'exists' => true]);
        \App\Model\Mkt\ReportDailyAdvertiser::$rows = [$report];
        $legacyService->planList('8', ['plan-1']);
        $legacyService->reportDailyAdvertiser('8', '2026-10-21');
        $legacyService->reportHourlyAdvertiser('8', '2026-10-21');
        $legacyService->reportDailyPlan('8', '2026-10-21');
        ensure($adapter->appInfo === ['auth_url' => '已有应用配置'], '原平台应用配置不得被广告主 extra 覆盖');
        ensure(MktPlan::$rows['plan-1']->writes === ($platform === 'jl' ? 0 : 1), '原平台计划分页同步次数发生变化');
        ensure($report->writes === 2 && $report->cost === 10, '原平台日报和小时报表更新行为发生变化');
        $adapter->empty = true;
        $legacyService->planList('8', ['plan-1']);
        $legacyService->reportDailyAdvertiser('8', '2026-10-21');
        $legacyService->reportHourlyAdvertiser('8', '2026-10-21');
        $legacyService->reportDailyPlan('8', '2026-10-21');
        $adapter->empty = false;
        $adapter->failSync = true;
        foreach (['planList', 'reportDailyAdvertiser', 'reportHourlyAdvertiser', 'reportDailyPlan'] as $method) {
            if ($platform === 'jl' && in_array($method, ['planList', 'reportDailyPlan'], true)) { continue; }
            blocked(function () use ($legacyService, $method) {
                if ($method === 'planList') { $legacyService->planList('8'); }
                else { $legacyService->$method('8', '2026-10-21'); }
            }, '同步失败');
        }
        ensure($report->writes === 2 && MktPlan::$rows['plan-1']->writes === ($platform === 'jl' ? 0 : 1), '空结果或同步失败不应改变已有数据');
        $legacySnapshots[$platform] = [
            'calls' => $adapter->calls,
            'user' => [$user->access_token, $user->access_time, $user->refresh_token, $user->refresh_time, $user->writes],
            'plans' => array_map(function ($row) { return (array)$row; }, MktPlan::$rows),
            'daily' => (array)$report,
            'planReports' => array_map(function ($row) { return (array)$row; }, \App\Model\Mkt\ReportDailyPlan::$rows),
        ];
    }
    if (defined('LEGACY_SNAPSHOT_PATH')) {
        file_put_contents(LEGACY_SNAPSHOT_PATH, json_encode($legacySnapshots, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    echo '通过：六个原平台授权续期、计划分页及日报小时报表回归' . PHP_EOL;
    echo '共通过 8 组持久化及筛选边界测试。' . PHP_EOL;
}
