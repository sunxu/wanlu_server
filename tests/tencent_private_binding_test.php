<?php
/** Isolated transactional account binding tests: no real DB, Redis or HTTP. */
declare(strict_types=1);

namespace App\Constants {
    class MktConst { const PLATFORM_TX = 'tx'; }
}
namespace Symfony\Component\Console\Input {
    class InputArgument { const REQUIRED = 1; }
    class InputOption { const VALUE_NONE = 1; }
}
namespace Hyperf\Command {
    class Command {
        public $input;
        public $lines = [];
        public function line($message) { $this->lines[] = $message; }
    }
}
namespace TxPrivateBindingTest {
    class Query {
        private $klass;
        private $filters = [];
        public function __construct($klass) { $this->klass = $klass; }
        public function where($field, $value) {
            $this->filters[$field] = (string)$value; return $this;
        }
        public function lockForUpdate() { return $this; }
        public function get() {
            $class = $this->klass;
            return array_values(array_filter($class::$records, function($r) {
                foreach($this->filters as $field => $value) {
                    if ((string)($r->$field ?? '') !== $value) return false;
                }
                return true;
            }));
        }
        public function first($columns = []) { return $this->get()[0] ?? null; }
        public function exists() { return count($this->get()) > 0; }
    }
    abstract class Model {
        public static $records = [];
        protected static $primary = 'id';
        public static $failSave = false;
        public static function find($id) { return static::$records[(string)$id] ?? null; }
        public static function query() { return new Query(static::class); }
        public function fill($fields) {
            foreach ($fields as $key => $value) $this->$key = $value;
            return $this;
        }
        public function save() {
            if (static::$failSave) return false;
            static::$records[(string)$this->{static::$primary}] = $this;
            return true;
        }
    }
    class Input {
        private $appCode;
        private $account;
        private $apply;
        public function __construct($appCode, $account, $apply) {
            $this->appCode=$appCode; $this->account=$account; $this->apply=$apply;
        }
        public function getArgument($key) {
            return $key==='app_code' ? $this->appCode : $this->account;
        }
        public function getOption($key) { return $key==='apply' ? $this->apply : false; }
    }
    function check($condition, $message) {
        if (!$condition) throw new \RuntimeException($message);
    }
    function fails(callable $fn, $expected) {
        try { $fn(); } catch (\RuntimeException $e) {
            check($e->getMessage() === $expected, 'unexpected error ' . $e->getMessage());
            return;
        }
        throw new \RuntimeException('expected error ' . $expected);
    }
}
namespace Hyperf\DbConnection {
    class Db {
        public static function transaction($callback) {
            // Restore model writes if any action in the transaction fails.
            $beforeAccount = \App\Model\Mkt\MktAccount::$records;
            $beforeAd = \App\Model\Mkt\MktAdvertiser::$records;
            try { return $callback(); } catch (\Throwable $e) {
                \App\Model\Mkt\MktAccount::$records = $beforeAccount;
                \App\Model\Mkt\MktAdvertiser::$records = $beforeAd;
                throw $e;
            }
        }
    }
}
namespace App\Model {
    class App extends \TxPrivateBindingTest\Model {
        public static $records = [];
        protected static $primary = 'app_code';
    }
}
namespace App\Model\Mkt {
    class MktUser extends \TxPrivateBindingTest\Model {
        public static $records = [];
        protected static $primary = 'user_id';
    }
    class MktApp extends \TxPrivateBindingTest\Model {
        public static $records = [];
        protected static $primary = 'app_id';
    }
    class MktAccount extends \TxPrivateBindingTest\Model {
        public static $records = [];
        protected static $primary = 'account_id';
        public static $failSave = false;
    }
    class MktAdvertiser extends \TxPrivateBindingTest\Model {
        public static $records = [];
        protected static $primary = 'advertiser_id';
        public static $failSave = false;
    }
}
namespace {
    use App\Model\App;
    use App\Model\Mkt\MktUser;
    use App\Model\Mkt\MktApp;
    use App\Model\Mkt\MktAccount;
    use App\Model\Mkt\MktAdvertiser;
    use App\Service\Mkt\TxPrivateCredentialId;
    use App\Service\Mkt\TencentPrivateBindingGuard as Guard;
    use function TxPrivateBindingTest\check;
    use function TxPrivateBindingTest\fails;

    $root=dirname(__DIR__);
    require $root . '/admin_server/app/Service/Mkt/TxPrivateCredentialId.php';
    require $root . '/admin_server/app/Service/Mkt/TencentPrivateBindingGuard.php';
    require $root . '/admin_server/app/Command/Mkt/TencentPrivateBind.php';

    class FakeBindCommand extends \App\Command\Mkt\TencentPrivateBind {
        public $remote;
        public $calls = 0;
        protected function fetchRemoteAdvertiser(
            string $clientId, string $clientSecret, string $accessToken, string $accountId
        ): array {
            $this->calls++;
            check($clientId==='1112045194' && $clientSecret==='TEST_SECRET'
                && $accessToken==='TEST_ACCESS', 'uses imported private credential');
            if ($this->remote instanceof \Throwable) throw $this->remote;
            return $this->remote;
        }
    }
    function record(array $fields) { return (object)$fields; }
    function bind($apply=false, $remote=null, $app='lzgjf', $account='94079761') {
        $c=new FakeBindCommand();
        $c->input=new \TxPrivateBindingTest\Input($app,$account,$apply);
        $c->remote=$remote===null
            ? [['account_id'=>$account,'advertiser_id'=>$account,
                'advertiser_name'=>'Tencent Verified Legal Name']]
            : $remote;
        $c->handle();
        check(count($c->lines)===1, 'one result');
        $text=$c->lines[0];
        foreach(['TEST_SECRET','TEST_ACCESS'] as $secret) {
            check(strpos($text,$secret)===false, 'secret must not be output');
        }
        return json_decode($text,true);
    }
    $credentialId=TxPrivateCredentialId::fromAppCode('lzgjf');
    App::$records['lzgjf']=record(['app_code'=>'lzgjf','status'=>1]);
    MktUser::$records[$credentialId]=record([
        'user_id'=>$credentialId,'app_id'=>'1112045194',
        'access_token'=>'TEST_ACCESS','access_time'=>time()+3600,
    ]);
    MktApp::$records['1112045194']=record([
        'app_id'=>'1112045194', 'app_secret'=>'TEST_SECRET','platform'=>'tx',
    ]);
    $result=bind();
    check($result['status']==='TX_BIND_DRY_RUN_READY'
        && $result['would_create_account']===true
        && $result['would_create_advertiser']===true
        && !$result['created_account'] && !$result['created_advertiser']
        && !$result['callback_enabled_by_command'], 'dry run describes proposed binding');
    check(MktAccount::$records===[] && MktAdvertiser::$records===[],
        'dry run must never create rows');
    echo '通过：只读预览，真实私有 Token 远端身份检查，零写库' . PHP_EOL;

    $r=bind(true);
    check($r['status']==='TX_BIND_APPLIED'
        && $r['created_account'] && $r['created_advertiser'], 'explicit apply creates two rows');
    check(MktAccount::$records['94079761']->user_id===$credentialId
        && MktAccount::$records['94079761']->is_valid===1
        && MktAccount::$records['94079761']->account_role==='PRIVATE_APP',
        'new account is associated with real private credential');
    check(MktAdvertiser::$records['94079761']->platform==='tx'
        && MktAdvertiser::$records['94079761']->app_code==='lzgjf'
        && MktAdvertiser::$records['94079761']->advertiser_name==='Tencent Verified Legal Name',
        'new advertiser must preserve actual remote name and app');
    $r=bind(true);
    check($r['status']==='TX_BIND_APPLIED'
        && !$r['created_account'] && !$r['created_advertiser'],
        'repeated apply must be idempotent');
    echo '通过：显式写入两个实体、真实来源名称、幂等且不自动开启 Callback' . PHP_EOL;

    $account=MktAccount::$records['94079761'];
    $ad=MktAdvertiser::$records['94079761'];
    check(Guard::inspect(['user_id'=>'other-oauth','is_valid'=>1],null,$credentialId,'lzgjf','94079761')['status']
        ==='TX_BIND_EXISTING_ACCOUNT_OWNER_HOLD','refuse existing OAuth owner');
    check(Guard::inspect(['user_id'=>$credentialId,'is_valid'=>0],null,$credentialId,'lzgjf','94079761')['status']
        ==='TX_BIND_EXISTING_ACCOUNT_DISABLED_HOLD','refuse disabled account');
    check(Guard::inspect(null,[
        'advertiser_id'=>'94079761','account_id'=>'94079761',
        'platform'=>'jl','app_code'=>'lzgjf','status'=>1,
    ],$credentialId,'lzgjf','94079761')['status']
        ==='TX_BIND_EXISTING_ADVERTISER_OWNER_HOLD','refuse JL override');
    check(Guard::inspect(null,[
        'advertiser_id'=>'94079761','account_id'=>'94079761',
        'platform'=>'tx','app_code'=>'another-app','status'=>1,
    ],$credentialId,'lzgjf','94079761')['status']
        ==='TX_BIND_EXISTING_ADVERTISER_OWNER_HOLD','refuse cross-app override');
    check(Guard::inspect(null,[
        'advertiser_id'=>'94079761','account_id'=>'94079761',
        'platform'=>'tx','app_code'=>'lzgjf','status'=>0,
    ],$credentialId,'lzgjf','94079761')['status']
        ==='TX_BIND_EXISTING_ADVERTISER_DISABLED_HOLD','refuse auto-reenable');
    $account->user_id='other-oauth';
    $r=bind(true);
    check($r['status']==='TX_BIND_EXISTING_ACCOUNT_OWNER_HOLD'
        && MktAccount::$records['94079761']->user_id==='other-oauth',
        'binding must not overwrite existing OAuth user');
    $account->user_id=$credentialId;
    echo '通过：账户/广告主主键冲突、跨 App、禁用和 OAuth 归属阻断' . PHP_EOL;

    MktAccount::$records=[];
    MktAdvertiser::$records=[];
    MktAdvertiser::$failSave=true;
    fails(function(){bind(true);}, 'TX_BIND_ADVERTISER_SAVE_FAILED');
    check(MktAccount::$records===[] && MktAdvertiser::$records===[],
        'transaction must rollback account if advertiser insertion fails');
    MktAdvertiser::$failSave=false;
    fails(function(){bind(false, [['account_id'=>'999','advertiser_id'=>'999','advertiser_name'=>'wrong']]);},
        'TX_BIND_REMOTE_ACCOUNT_MISMATCH');
    fails(function(){bind(false, [['account_id'=>'94079761','advertiser_id'=>'94079761']]);},
        'TX_BIND_REMOTE_ADVERTISER_NAME_INVALID');
    fails(function(){bind(false, new \RuntimeException('TOKEN=TEST_ACCESS'));},
        'TX_BIND_TENCENT_READ_FAILED');
    fails(function(){bind(false,null,'lzgjf','not_an_id');},
        'TX_BIND_INVALID_ACCOUNT_ID');
    MktUser::$records[$credentialId]->access_time=time()-1;
    fails(function(){bind(true);}, 'TX_BIND_CREDENTIAL_NOT_READY');
    echo '通过：事务回滚、远端不匹配、名称缺失、凭证失效及异常脱敏' . PHP_EOL;
    echo '共通过 4 组腾讯私有广告主安全绑定隔离测试。' . PHP_EOL;
}
