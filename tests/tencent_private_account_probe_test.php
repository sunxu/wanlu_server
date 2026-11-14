<?php
/** Safe read-only account probe regressions (mock Tencent API and DB; never send real credentials). */
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
namespace TxAccountProbeTest {
    class Query {
        private $class;
        private $filters = [];
        public function __construct(string $class) { $this->class = $class; }
        public function where($column, $value) {
            $this->filters[$column] = (string)$value;
            return $this;
        }
        public function exists() {
            foreach ($this->class::$records as $item) {
                $matching = true;
                foreach ($this->filters as $col => $value) {
                    if ((string)($item->$col ?? '') !== $value) $matching = false;
                }
                if ($matching) return true;
            }
            return false;
        }
    }
    abstract class Model {
        public static $records = [];
        public static function query() { return new Query(static::class); }
        public static function find($key) { return static::$records[$key] ?? null; }
    }
    class Input {
        private $appCode;
        private $accountId;
        public function __construct($appCode, $accountId) {
            $this->appCode = $appCode;
            $this->accountId = $accountId;
        }
        public function getArgument($name) {
            return $name === 'app_code' ? $this->appCode : $this->accountId;
        }
    }
    function check($condition, $message) {
        if (!$condition) throw new \RuntimeException($message);
    }
    function expectCode(callable $fn, string $code) {
        try {
            $fn();
        } catch (\RuntimeException $e) {
            check($e->getMessage() === $code, 'wrong safe code: ' . $e->getMessage());
            return;
        }
        throw new \RuntimeException('missing safe code ' . $code);
    }
}
namespace App\Model {
    class App extends \TxAccountProbeTest\Model { public static $records = []; }
}
namespace App\Model\Mkt {
    class MktUser extends \TxAccountProbeTest\Model { public static $records = []; }
    class MktApp extends \TxAccountProbeTest\Model { public static $records = []; }
    class MktAdvertiser extends \TxAccountProbeTest\Model { public static $records = []; }
    class MktAccount extends \TxAccountProbeTest\Model { public static $records = []; }
}
namespace {
    use App\Model\App;
    use App\Model\Mkt\MktUser;
    use App\Model\Mkt\MktApp;
    use App\Model\Mkt\MktAdvertiser;
    use App\Model\Mkt\MktAccount;
    use App\Service\Mkt\TxPrivateCredentialId;
    use function TxAccountProbeTest\check;
    use function TxAccountProbeTest\expectCode;

    $root = dirname(__DIR__);
    require $root . '/admin_server/app/Service/Mkt/TxPrivateCredentialId.php';
    require $root . '/admin_server/app/Library/Mkt/TencentAdvertiserProbeError.php';
    require $root . '/admin_server/app/Command/Mkt/TencentPrivateAccountProbe.php';

    class FakeProbe extends \App\Command\Mkt\TencentPrivateAccountProbe {
        public $requests = [];
        public $result = [];
        protected function fetchTencentAdvertiser(
            string $clientId, string $secret, string $access, string $accountId
        ): array {
            $this->requests[] = [$clientId, $secret, $access, $accountId];
            if ($this->result instanceof \Throwable) throw $this->result;
            return $this->result;
        }
    }
    function record($values) { return (object)$values; }
    function probe($appCode = 'lzgjf', $accountId = '998877', $response = null) {
        $p = new FakeProbe();
        $p->input = new \TxAccountProbeTest\Input($appCode,$accountId);
        $p->result = $response === null
            ? [['account_id' => $accountId, 'advertiser_name'=>'TEST_ACCOUNT']]
            : $response;
        return $p;
    }

    $now = time();
    $id = TxPrivateCredentialId::fromAppCode('lzgjf');
    App::$records = [record(['app_code'=>'lzgjf','status'=>1])];
    MktUser::$records[$id] = record([
        'user_id'=>$id, 'app_id'=>'1112045194',
        'access_token'=>'SENSITIVE_ACCESS', 'access_time'=>$now+7200,
    ]);
    MktApp::$records['1112045194'] = record([
        'app_id'=>'1112045194','platform'=>'tx','app_secret'=>'SENSITIVE_SECRET',
    ]);

    $p = probe();
    $p->handle();
    $raw = $p->lines[0] ?? '';
    $v = json_decode($raw,true);
    check($v['status'] === 'REMOTE_ACCESS_PASS__MANUAL_BINDING_REQUIRED',
        'read-only probe must not auto-bind');
    check($v['remote_advertiser_accessible'] === true
        && $v['verified_account_id'] === '998877'
        && $v['database_modified'] === false
        && $v['token_refreshed'] === false
        && $v['callback_enabled'] === false, 'probe must remain read only');
    check($p->requests === [['1112045194','SENSITIVE_SECRET','SENSITIVE_ACCESS','998877']],
        'probe must use selected private app credential');
    foreach (['SENSITIVE_SECRET','SENSITIVE_ACCESS','TEST_ACCOUNT'] as $secret) {
        check(strpos($raw,$secret) === false, 'probe must not print sensitive info');
    }
    echo '通过：真实账户 GET 模拟访问且无数据库修改或敏感输出' . PHP_EOL;

    MktAdvertiser::$records['998877'] = record([
        'advertiser_id'=>'998877','account_id'=>'998877',
        'app_code'=>'otherapp','platform'=>'tx',
    ]);
    MktAccount::$records['998877'] = record(['account_id'=>'998877','is_valid'=>1]);
    $p = probe();
    $p->handle();
    $v = json_decode($p->lines[0],true);
    check($v['status'] === 'REMOTE_ACCESS_PASS__EXISTING_OWNERSHIP_HOLD'
        && $v['global_advertiser_id_conflict'] === true
        && $v['existing_mkt_account'] === true,
        'existing ownership must block automatic binding');
    echo '通过：广告主主键已在其他 App 下使用时 HOLD' . PHP_EOL;

    $p = probe('lzgjf','998877', [['account_id'=>'555','advertiser_name'=>'WRONG']]);
    expectCode(function () use ($p) { $p->handle(); },
        'TX_ACCOUNT_PROBE_REMOTE_IDENTITY_MISMATCH');
    $p = probe('lzgjf','998877',new \RuntimeException(
        'SENSITIVE_ACCESS https://api.e.qq.com/v3.0/advertiser/get?access_token=SENSITIVE_ACCESS'
    ));
    expectCode(function () use ($p) { $p->handle(); },
        'TX_ACCOUNT_PROBE_REMOTE_ADVERTISER_GET_FAILED');
    // Upstream API status and Tencent's numeric code are safe; freeform body is not.
    $p = probe('lzgjf','998877',new \App\Library\Mkt\TencentAdvertiserProbeError(
        \App\Library\Mkt\TencentAdvertiserProbeError::API_REJECTED, 200, 11002
    ));
    expectCode(function () use ($p) { $p->handle(); },
        'TX_ACCOUNT_PROBE_API_REJECTED HTTP_STATUS=200 API_CODE=11002');
    $p = probe('lzgjf','998877',new \App\Library\Mkt\TencentAdvertiserProbeError(
        \App\Library\Mkt\TencentAdvertiserProbeError::HTTP_FAILED, 403, 11000
    ));
    expectCode(function () use ($p) { $p->handle(); },
        'TX_ACCOUNT_PROBE_HTTP_FAILED HTTP_STATUS=403 API_CODE=11000');
    echo '通过：上游安全数字错误码可用于排查账户权限' . PHP_EOL;

    $p = probe('lzgjf','1112045194');
    MktUser::$records[$id]->access_time = $now - 1;
    expectCode(function () use ($p) { $p->handle(); },
        'TX_ACCOUNT_PROBE_ACCESS_TOKEN_EXPIRED');
    check(count($p->requests) === 0, 'expired access must not call Tencent');
    $p = probe('lzgjf','bad_id');
    expectCode(function () use ($p) { $p->handle(); },
        'TX_ACCOUNT_PROBE_INVALID_ACCOUNT_ID');
    echo '通过：远端身份不匹配、拒绝、过期及无效输入均安全拒绝' . PHP_EOL;
    echo '共通过 4 组腾讯私有账户权限探测测试。' . PHP_EOL;
}
