<?php
/** 私有 Token 导入隔离测试：模拟数据库和腾讯刷新，不访问外网、不保存真实凭证。 */
declare(strict_types=1);

namespace App\Constants {
    class MktConst { const PLATFORM_TX = 'tx'; }
}
namespace TencentPrivateTest {
    class Query {
        private $className;
        private $conditions = [];
        public function __construct(string $className) { $this->className = $className; }
        public function where($field, $operator, $value = null) {
            if (func_num_args() === 2) { $value = $operator; $operator = '='; }
            $this->conditions[] = [$field, $operator, $value];
            return $this;
        }
        public function lockForUpdate() { return $this; }
        public function get($fields = []) {
            $className = $this->className;
            return array_values(array_filter($className::$rows, function ($row) {
                foreach ($this->conditions as $condition) {
                    [$field, $operator, $value] = $condition;
                    if ($operator !== '=' || (string)($row->$field ?? '') !== (string)$value) return false;
                }
                return true;
            }));
        }
        public function first() { return $this->get()[0] ?? null; }
        public function exists() { return $this->first() !== null; }
    }
    abstract class Model {
        public static $rows = [];
        public static function query() { return new Query(static::class); }
        public static function find($id) { return static::$rows[(string)$id] ?? null; }
        public function save() { static::$rows[(string)$this->{static::key()}] = $this; return true; }
        abstract public static function key(): string;
    }
    function check($condition, $message) {
        if (!$condition) throw new \RuntimeException($message);
    }
    function rejects($call, $message) {
        try { $call(); } catch (\Exception $e) { return; }
        throw new \RuntimeException($message);
    }
}
namespace App\Model {
    class App extends \TencentPrivateTest\Model {
        public static $rows = [];
        public static function key(): string { return 'app_code'; }
    }
}
namespace App\Model\Mkt {
    class MktApp extends \TencentPrivateTest\Model {
        public static $rows = [];
        public static function key(): string { return 'app_id'; }
    }
    class MktUser extends \TencentPrivateTest\Model {
        public static $rows = [];
        public static function key(): string { return 'user_id'; }
    }
}
namespace Hyperf\DbConnection {
    class Db {
        public static function transaction(callable $callback) { return $callback(); }
    }
}
namespace {
    use App\Model\App;
    use App\Model\Mkt\MktApp;
    use App\Model\Mkt\MktUser;
    use App\Service\Mkt\TxPrivateCredentialId;
    use TencentPrivateTest\Query;
    use function TencentPrivateTest\check;
    use function TencentPrivateTest\rejects;

    $root = dirname(__DIR__);
    require $root . '/admin_server/app/Service/Mkt/TxPrivateCredentialId.php';
    require $root . '/admin_server/app/Service/Mkt/TencentPrivateImportError.php';
    require $root . '/admin_server/app/Service/Mkt/TencentPrivateCredentialService.php';

    class FakeImport extends \App\Service\Mkt\TencentPrivateCredentialService {
        public $responses = [];
        public $refreshCalls = [];
        protected function refreshToken(string $clientId, string $secret, string $refreshToken): array {
            $this->refreshCalls[] = [$clientId, $secret, $refreshToken];
            $result = array_shift($this->responses);
            if ($result instanceof \Throwable) throw $result;
            return $result;
        }
    }
    function rejectsWithCode(callable $callback, string $code): void {
        try {
            $callback();
        } catch (\App\Service\Mkt\TencentPrivateImportError $e) {
            check($e->getMessage() === $code, '实际错误代码与预期不符：' . $e->getMessage());
            return;
        }
        throw new \RuntimeException('应产生安全失败代码：' . $code);
    }
    function fixture(string $code): void {
        $app = new App();
        $app->app_code = $code;
        $app->status = 1;
        $app->save();
    }
    function params(string $refresh, bool $replace = false): array {
        return [
            'client_id' => '1234567890', 'client_secret' => 'test-secret',
            'refresh_token' => $refresh, 'replace_existing' => $replace,
        ];
    }
    $now = time();
    fixture('bxllzs');
    fixture('lzgjf');
    $svc = new FakeImport();
    $svc->responses[] = [
        'access_token' => 'access-1', 'access_time' => $now + 3600,
        'refresh_token' => 'new-refresh-1', 'refresh_time' => $now + 5000,
    ];
    $id = $svc->import('bxllzs', params('refresh-1'));
    check($id === TxPrivateCredentialId::fromAppCode('bxllzs'), '凭证 ID 应由 app_code 生成');
    check(MktUser::find($id)->access_token === 'access-1'
        && MktUser::find($id)->refresh_token === 'new-refresh-1'
        && MktUser::find($id)->access_time === $now + 3600
        && MktUser::find($id)->refresh_time === $now + 5000, '必须保存腾讯刷新结果和实际时限');
    check(MktApp::find('1234567890')->app_secret === 'test-secret', '开发者应用密钥必须在 mkt_app');
    check(count($svc->refreshCalls) === 1, '导入必须调用腾讯接口获取 Access Token');
    echo '通过：Refresh Token 自动换取 Access Token 和有效期' . PHP_EOL;

    rejects(function () use ($svc) { $svc->import('bxllzs', params('refresh-1')); },
        '已有记录且未传 --replace 应拒绝');
    check(count($svc->refreshCalls) === 1, '覆盖校验必须在调用腾讯接口之前');
    echo '通过：拒绝无意覆盖且无需重复远程刷新' . PHP_EOL;

    // Refresh API 偶尔不提供 refresh_token_expires_in，数据库以 0 标记未知。
    $svc->responses[] = ['access_token' => 'access-2', 'access_time' => $now + 7200];
    $id2 = $svc->import('lzgjf', params('refresh-2'));
    check(MktUser::find($id2)->refresh_token === 'refresh-2'
        && MktUser::find($id2)->refresh_time === 0,
        '不允许伪造缺失的 Refresh Token 到期时间');
    check($id !== $id2, '不同业务 App 不得复用凭证记录');
    echo '通过：未知 Refresh Token 有效期记为 0，不伪造到期时间' . PHP_EOL;

    rejects(function () use ($svc) { $svc->import('lzgjf', params('new-refresh-1', true)); },
        '不得导入其他业务 App 正在使用的 Refresh Token');
    echo '通过：跨 App 重复 Token 保护' . PHP_EOL;

    $old = MktUser::find($id)->access_token;
    $svc->responses[] = new \RuntimeException('腾讯接口拒绝刷新');
    rejects(function () use ($svc) { $svc->import('bxllzs', params('refresh-new', true)); },
        '腾讯刷新失败时必须拒绝导入');
    check(MktUser::find($id)->access_token === $old, '失败不能修改旧凭证');
    echo '通过：远程刷新失败不修改原记录' . PHP_EOL;

    $svc->responses[] = ['access_token' => 'access-3', 'access_time' => $now + 3600];
    $svc->import('bxllzs', params('refresh-new', true));
    check(MktUser::find($id)->access_token === 'access-3'
        && MktUser::find($id)->refresh_token === 'refresh-new'
        && MktUser::find($id)->refresh_time === 0, '--replace 更新且缺省有效期不伪造');
    echo '通过：显式替换私有凭证' . PHP_EOL;

    $before = count(MktUser::$rows);
    $svc->responses[] = ['access_token' => 'invalid', 'access_time' => 0];
    rejects(function () use ($svc) { $svc->import('lzgjf', params('replacement', true)); },
        '腾讯接口没有有效到期时间时必须拒绝');
    check(count(MktUser::$rows) === $before, '无效 TTL 不得创建额外记录');
    echo '通过：缺失有效 Access Token 时限时失败关闭' . PHP_EOL;

    rejects(function () use ($svc) { $svc->import('INVALID', params('x')); }, '非法 App Code 拒绝');
    rejects(function () use ($svc) { $svc->import('not_exists', params('x')); }, '不存在业务 App 拒绝');
    rejects(function () use ($svc) { $svc->import('bxllzs', ['client_id' => '123']); }, '缺失密钥拒绝');
    echo '通过：参数格式和业务 App 存在性检查' . PHP_EOL;

    // 这组测试直接校验固定失败代码，确保真实令牌或腾讯响应不泄露到 CLI。
    rejectsWithCode(function () use ($svc) { $svc->import('INVALID', params('x')); },
        \App\Service\Mkt\TencentPrivateImportError::INVALID_INPUT);
    rejectsWithCode(function () use ($svc) { $svc->import('not_exists', params('x')); },
        \App\Service\Mkt\TencentPrivateImportError::APP_NOT_ACTIVE);
    $tooLong = params('test-refresh');
    $tooLong['client_secret'] = str_repeat('S', 101);
    rejectsWithCode(function () use ($svc, $tooLong) { $svc->import('bxllzs', $tooLong); },
        \App\Service\Mkt\TencentPrivateImportError::SECRET_STORAGE_LIMIT);
    rejectsWithCode(function () use ($svc) { $svc->import('bxllzs', params('another-refresh')); },
        \App\Service\Mkt\TencentPrivateImportError::CREDENTIAL_ALREADY_EXISTS);
    $svc->responses[] = new \RuntimeException('远端错误携带机密 refresh_token=NEVER_PRINT');
    rejectsWithCode(function () use ($svc) { $svc->import('bxllzs', params('unused-refresh', true)); },
        \App\Service\Mkt\TencentPrivateImportError::TENCENT_REFRESH_REJECTED);
    $svc->responses[] = new \RuntimeException('腾讯营销请求失败：/oauth/token');
    rejectsWithCode(function () use ($svc) { $svc->import('bxllzs', params('unused-refresh', true)); },
        \App\Service\Mkt\TencentPrivateImportError::TENCENT_NETWORK_FAILED);
    echo '通过：结构化安全失败代码与密钥脱敏' . PHP_EOL;
    echo '共通过 9 组腾讯私有 Token 导入测试。' . PHP_EOL;
}
