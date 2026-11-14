<?php
/** Read-only preflight tests. No database, Redis, network or real credentials. */
declare(strict_types=1);

namespace App\Constants {
    class MktConst { const PLATFORM_TX = 'tx'; }
}
namespace Symfony\Component\Console\Input {
    class InputArgument { const REQUIRED = 1; }
}
namespace Hyperf\Command {
    class Command {
        public $input;
        public $lines = [];
        public function line($message) { $this->lines[] = $message; }
    }
}
namespace TxPreflightTest {
    class Query {
        private $className;
        private $filters = [];
        public function __construct(string $className) { $this->className = $className; }
        public function where($column, $value) {
            $this->filters[$column] = (string)$value;
            return $this;
        }
        public function get($columns = []) {
            $class = $this->className;
            return array_values(array_filter($class::$records, function ($rec) {
                foreach ($this->filters as $col => $val) {
                    if ((string)($rec->$col ?? '') !== $val) return false;
                }
                return true;
            }));
        }
        public function exists() { return count($this->get()) > 0; }
        public function first($columns = []) { return $this->get($columns)[0] ?? null; }
    }
    abstract class Model {
        public static $records = [];
        public static function query() { return new Query(static::class); }
    }
    function check($condition, string $reason) {
        if (!$condition) throw new \RuntimeException($reason);
    }
    class Input {
        private $appCode;
        public function __construct(string $appCode) { $this->appCode = $appCode; }
        public function getArgument($name) { return $this->appCode; }
    }
}
namespace App\Model {
    class App extends \TxPreflightTest\Model { public static $records = []; }
}
namespace App\Model\Mkt {
    class MktUser extends \TxPreflightTest\Model { public static $records = []; }
    class MktApp extends \TxPreflightTest\Model { public static $records = []; }
    class MktAdvertiser extends \TxPreflightTest\Model { public static $records = []; }
    class MktAccount extends \TxPreflightTest\Model { public static $records = []; }
}
namespace {
    use App\Model\App;
    use App\Model\Mkt\MktUser;
    use App\Model\Mkt\MktApp;
    use App\Model\Mkt\MktAdvertiser;
    use App\Model\Mkt\MktAccount;
    use App\Service\Mkt\TxPrivateCredentialId;
    use function TxPreflightTest\check;

    $GLOBALS['tx_cron_enabled'] = true;
    function config($key, $fallback = null) {
        return $key === 'crontab.enable' ? $GLOBALS['tx_cron_enabled'] : $fallback;
    }
    $root = dirname(__DIR__);
    require $root . '/admin_server/app/Service/Mkt/TxPrivateCredentialId.php';
    require $root . '/admin_server/app/Service/Mkt/TencentPrivateRefreshWindow.php';
    require $root . '/admin_server/app/Command/Mkt/TencentPrivatePreflight.php';

    function record(array $values) { return (object)$values; }
    function inspect() {
        $c = new \App\Command\Mkt\TencentPrivatePreflight();
        $c->input = new \TxPreflightTest\Input('lzgjf');
        $c->handle();
        return $c->lines[0];
    }

    $now = time();
    $id = TxPrivateCredentialId::fromAppCode('lzgjf');
    App::$records = [record(['app_code'=>'lzgjf', 'status'=>1])];
    MktUser::$records = [record([
        'user_id'=>$id, 'app_id'=>'1112045194',
        'access_token'=>'SENSITIVE_ACCESS_TOKEN', 'access_time'=>$now+30*86400,
        'refresh_token'=>'SENSITIVE_REFRESH_TOKEN', 'refresh_time'=>$now+30*86400,
    ])];
    MktApp::$records = [record([
        'app_id'=>'1112045194','platform'=>'tx', 'app_secret'=>'SENSITIVE_APP_SECRET',
    ])];
    MktAdvertiser::$records = [record([
        'advertiser_id'=>'998877', 'account_id'=>'998877',
        'app_code'=>'lzgjf','platform'=>'tx','status'=>1,
    ])];
    MktAccount::$records = [record(['account_id'=>'998877','is_valid'=>1])];

    $raw = inspect();
    $r = json_decode($raw, true);
    check($r['status'] === 'LOCAL_PREFLIGHT_PASS__EXTERNAL_INTEGRATION_HOLD', 'local checks pass');
    check($r['advertisers']['enabled_count'] === 1
        && $r['advertisers']['valid_account_count'] === 1, 'valid real account mapping checked');
    check(in_array('REFRESH_EXPIRES_NOT_LATER_THAN_ACCESS', $r['warnings'], true),
        'identical token expiry must emit risk warning');
    foreach (['SENSITIVE_ACCESS_TOKEN','SENSITIVE_REFRESH_TOKEN','SENSITIVE_APP_SECRET'] as $secret) {
        check(strpos($raw,$secret) === false, 'preflight must never expose credentials');
    }
    check($r['database_modified'] === false
        && $r['network_requests'] === 0
        && $r['callback_enabled_by_command'] === false, 'preflight must remain read-only');
    echo '通过：本地绑定成功、等期预警与零敏感信息输出' . PHP_EOL;

    MktAccount::$records = [];
    $r = json_decode(inspect(), true);
    check($r['status'] === 'LOCAL_PREFLIGHT_HOLD'
        && !$r['checks']['every_advertiser_account_valid']
        && $r['advertisers']['invalid_or_missing_account_count'] === 1,
        'missing real account must block local preflight');
    echo '通过：缺失真实账户绑定时保持 HOLD' . PHP_EOL;

    $GLOBALS['tx_cron_enabled'] = 'false';
    MktAccount::$records = [record(['account_id'=>'998877','is_valid'=>1])];
    $r = json_decode(inspect(), true);
    check($r['status'] === 'LOCAL_PREFLIGHT_HOLD'
        && !$r['checks']['crontab_config_enabled']
        && in_array('CRONTAB_NOT_ENABLED_IN_THIS_PROCESS', $r['warnings'], true),
        'CRONTAB_ENABLE=false must be explicit hold');
    echo '通过：定时任务开关关闭时 HOLD' . PHP_EOL;
    echo '共通过 3 组只读联调预检测试。' . PHP_EOL;
}
