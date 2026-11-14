<?php
/** Isolated scheduler regression: no DB, Tencent API, Redis or real queues. */
declare(strict_types=1);

namespace App\Constants {
    class MktConst { const QUEUE_KEY = 'mkt'; }
}
namespace Symfony\Component\Console\Input {
    class InputArgument { const OPTIONAL = 2; }
}
namespace Hyperf\Command {
    class Command { public $input; }
}
namespace App\Job\Mkt {
    class MktJob {
        public $func;
        public $args;
        public function __construct(string $func, array $args) {
            $this->func = $func;
            $this->args = $args;
        }
    }
}
namespace App\Model\Mkt {
    class FakeQuery {
        private $filters = [];
        public function where($key, $operator, $value = null) {
            if (func_num_args() === 2) {
                $value = $operator;
                $operator = '=';
            }
            $this->filters[] = [$key, $operator, $value];
            return $this;
        }
        public function pluck($key) {
            $rows = array_filter(MktUser::$rows, function ($row) {
                foreach ($this->filters as [$field, $op, $value]) {
                    $actual = $row[$field];
                    if ($op === '=' && (string)$actual !== (string)$value) return false;
                    if ($op === '<' && !($actual < $value)) return false;
                    if ($op === '>' && !($actual > $value)) return false;
                    if ($op === 'like' && !preg_match('/^' . str_replace(
                        ['%', '_'], ['.*', '.'], preg_quote((string)$value, '/')
                    ) . '$/D', (string)$actual)) return false;
                }
                return true;
            });
            return new class(array_values(array_column($rows, $key))) {
                private $items;
                public function __construct($items) { $this->items = $items; }
                public function toArray() { return $this->items; }
            };
        }
    }
    class MktUser {
        public static $rows = [];
        public static function query() { return new FakeQuery(); }
    }
}
namespace {
    use App\Job\Mkt\MktJob;
    use App\Model\Mkt\MktUser;
    use App\Service\Mkt\TencentPrivateRefreshWindow;
    use App\Service\Mkt\TxPrivateCredentialId;

    $root = dirname(__DIR__);
    require $root . '/admin_server/app/Service/Mkt/TxPrivateCredentialId.php';
    require $root . '/admin_server/app/Service/Mkt/TencentPrivateRefreshWindow.php';
    require $root . '/admin_server/app/Command/Mkt/AuthToken.php';

    $GLOBALS['tx_test_enqueues'] = [];
    function queue_push($job, $queue) {
        $GLOBALS['tx_test_enqueues'][] = [$job->args[0], $job->func, $queue];
    }
    function check($ok, $reason) {
        if (!$ok) throw new \RuntimeException($reason);
    }
    class FakeInput {
        private $value;
        public function __construct($value = null) { $this->value = $value; }
        public function getArgument($key) { return $this->value; }
    }
    function runScheduler($value = null) {
        $GLOBALS['tx_test_enqueues'] = [];
        $command = new \App\Command\Mkt\AuthToken();
        $command->input = new FakeInput($value);
        $command->handle();
        return array_column($GLOBALS['tx_test_enqueues'], 0);
    }
    $now = time();
    $id = TxPrivateCredentialId::fromAppCode('lzgjf');
    $id2 = TxPrivateCredentialId::fromAppCode('bxllzs');
    check(TencentPrivateRefreshWindow::shouldEnqueue(
        $id, $now + 23 * 3600, $now + 23 * 3600, $now
    ), '私有 Token 要提前24小时');
    check(!TencentPrivateRefreshWindow::shouldEnqueue(
        $id, $now + 24 * 3600 + 30, $now + 24 * 3600 + 30, $now
    ), '不应提前超过24小时频繁刷新');
    check(TencentPrivateRefreshWindow::shouldEnqueue(
        $id, $now + 10 * 86400, $now + 4 * 3600, $now
    ), 'Refresh Token 比 Access Token 先到期也需提前刷新');
    check(!TencentPrivateRefreshWindow::shouldEnqueue(
        'oauth-user', $now + 30 * 60, $now + 5 * 86400, $now
    ), '旧平台不能提前24小时刷新');
    check(TencentPrivateRefreshWindow::shouldEnqueue(
        'oauth-user', $now + 15 * 60, $now + 5 * 86400, $now
    ), '旧平台20分钟窗口保持不变');
    echo '通过：私有提前24小时、其他平台仍为20分钟' . PHP_EOL;

    MktUser::$rows = [
        ['user_id'=>$id, 'access_time'=>$now + 23 * 3600, 'refresh_time'=>$now + 23 * 3600],
        ['user_id'=>$id2, 'access_time'=>$now + 30 * 86400, 'refresh_time'=>$now + 5 * 3600],
        ['user_id'=>'oauth-due', 'access_time'=>$now + 100, 'refresh_time'=>0],
        ['user_id'=>'oauth-30m', 'access_time'=>$now + 1800, 'refresh_time'=>0],
        ['user_id'=>'txappX' . str_repeat('0', 40), 'access_time'=>$now + 3600, 'refresh_time'=>0],
    ];
    $ids = runScheduler();
    sort($ids);
    $expected = [$id, $id2, 'oauth-due'];
    sort($expected);
    check($ids === $expected, '实际队列中应仅有到期的私有与原版 Token');
    check(count($GLOBALS['tx_test_enqueues']) === 3, '每个凭证最多入队一次');
    foreach ($GLOBALS['tx_test_enqueues'] as $entry) {
        check($entry[1] === 'authToken' && $entry[2] === 'mkt',
            '队列仍调用现有 MktService::authToken');
    }
    echo '通过：真实 CLI 调度选择、去重、严格私有 ID 验证' . PHP_EOL;
    check(runScheduler($id) === [$id], '命令指定 user_id 时不得入队其他 Token');
    check(runScheduler('oauth-30m') === [], '单条 Token 未到刷新窗口时保持空队列');
    echo '通过：指定 user_id 执行时保留筛选语义' . PHP_EOL;
    echo '共通过 3 组腾讯私有刷新调度测试。' . PHP_EOL;
}
