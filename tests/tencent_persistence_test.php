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
    class ReportDailyAdvertiser extends \TencentTest\Model { public static $rows = []; }
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
        ensure($report->writes === 2 && $adapter->extra === $extra, '各平台应共用报表更新和扩展配置传递');
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
    echo '共通过 7 组持久化及筛选边界测试。' . PHP_EOL;
}
