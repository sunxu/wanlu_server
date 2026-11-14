<?php
/** Offline safe HTTP/API error diagnostics tests for Tencent private account probe. */
declare(strict_types=1);

namespace GuzzleHttp {
    class Client {
        public $responses = [];
        public $requests = [];
        public function request($method, $uri, $options) {
            $this->requests[] = [$method, $uri, $options];
            $response = array_shift($this->responses);
            if ($response instanceof \Throwable) throw $response;
            $status = $response[0];
            $body = $response[1];
            return new class($status, $body) {
                private $status;
                private $body;
                public function __construct($status, $body) {
                    $this->status = $status;
                    $this->body = $body;
                }
                public function getStatusCode() { return $this->status; }
                public function getBody() { return $this->body; }
            };
        }
    }
}
namespace {
    use App\Library\Mkt\MktTx;
    use App\Library\Mkt\TencentAdvertiserProbeError as ProbeError;

    $root = dirname(__DIR__);
    require $root . '/admin_server/app/Library/Mkt/MktAbstract.php';
    require $root . '/admin_server/app/Library/Mkt/TencentAdvertiserProbeError.php';
    require $root . '/admin_server/app/Library/Mkt/MktTx.php';

    class ProbeTx extends MktTx {
        public $http;
        public function __construct() { $this->http = new \GuzzleHttp\Client(); }
        public function getClient(string $baseUri = null): \GuzzleHttp\Client {
            return $this->http;
        }
    }
    function probeCheck($ok, $reason) {
        if (!$ok) throw new \RuntimeException($reason);
    }
    function response($code, $data, $message = '') {
        return json_encode(['code'=>$code,'data'=>$data,'message_cn'=>$message]);
    }
    function errorOf(ProbeTx $tx, string $expectedReason) {
        try {
            $tx->advertiserProbe('94946190');
        } catch (ProbeError $e) {
            probeCheck($e->getMessage() === $expectedReason, 'expected safe reason ' . $expectedReason);
            return $e;
        }
        throw new \RuntimeException('missing safe failure ' . $expectedReason);
    }

    $tx = (new ProbeTx())->setToken('SENSITIVE_ACCESS_TOKEN');
    $tx->http->responses[] = [200, response(0, ['list'=>[['account_id'=>94946190, 'corporation_name'=>'PRIVATE_CORPORATION']]])];
    $info = $tx->advertiserProbe('94946190');
    probeCheck($info === [['account_id'=>'94946190']], 'must project only account id');
    [$method,$path,$options] = $tx->http->requests[0];
    probeCheck($method === 'GET' && $path === '/v3.0/advertiser/get', 'correct endpoint');
    probeCheck($options['query']['account_id'] === '94946190'
        && $options['query']['fields'] === '["account_id"]'
        && $options['query']['access_token'] === 'SENSITIVE_ACCESS_TOKEN'
        && strlen($options['query']['nonce']) === 32, 'minimum read-only query');
    // 132100 regression: even account-scoped queries require pagination.
    probeCheck($options['query']['pagination_mode'] === 'PAGINATION_MODE_NORMAL'
        && $options['query']['page'] === 1
        && $options['query']['page_size'] === 100
        && $options['http_errors'] === false && $options['allow_redirects'] === false,
        'Tencent requires pagination_mode and page_size for advertiser/get');
    probeCheck(strpos(json_encode($info),'PRIVATE_CORPORATION') === false,
        'do not return corporation name');
    echo '通过：单账户只读查询包含腾讯必需的分页字段、响应脱敏' . PHP_EOL;

    $tx->http->responses[] = new \RuntimeException(
        'https://api.e.qq.com/v3.0/advertiser/get?access_token=SENSITIVE_ACCESS_TOKEN'
    );
    $e = errorOf($tx, ProbeError::NETWORK_FAILED);
    probeCheck(strpos($e->getMessage(),'SENSITIVE_ACCESS_TOKEN') === false
        && $e->getPrevious() === null,'transport exception must not leak token or previous');
    $tx->http->responses[] = [403, response(11002, [], 'SENSITIVE_ACCESS_TOKEN forbidden')];
    $e = errorOf($tx, ProbeError::HTTP_FAILED);
    probeCheck($e->httpStatus() === 403 && $e->apiCode() === 11002
        && strpos($e->getMessage(), 'SENSITIVE_ACCESS_TOKEN') === false,
        'HTTP and API code only');
    // Tencent live response 132100 must be observable as a distinct API code.
    $tx->http->responses[] = [200, response(132100, [], 'missing pagination_mode/page_size')];
    $e = errorOf($tx, ProbeError::API_REJECTED);
    probeCheck($e->apiCode() === 132100, 'must retain Tencent parameter error 132100');
    $tx->http->responses[] = [200, response(11000, [], 'SENSITIVE_ACCESS_TOKEN rejected')];
    $e = errorOf($tx, ProbeError::API_REJECTED);
    probeCheck($e->httpStatus() === 200 && $e->apiCode() === 11000,
        'nonzero numeric API code visible without message');
    echo '通过：网络、HTTP 和腾讯业务错误分项且无敏感数据泄露' . PHP_EOL;

    $tx->http->responses[] = [200, 'NOT_JSON SECRET_RESPONSE'];
    errorOf($tx, ProbeError::RESPONSE_INVALID);
    $tx->http->responses[] = [200, response(0, [])];
    errorOf($tx, ProbeError::RESPONSE_INVALID);
    $tx->http->responses[] = [200, response(0, ['list'=>[]])];
    errorOf($tx, ProbeError::ACCOUNT_NOT_RETURNED);
    $tx->http->responses[] = [200, response(0, ['list'=>[['other_field'=>'noid']]])];
    errorOf($tx, ProbeError::RESPONSE_INVALID);
    $tx->http->responses[] = [200, response(0, ['list'=>[['account_id'=>'12345']]])];
    probeCheck($tx->advertiserProbe('94946190') === [['account_id'=>'12345']],
        'caller must reject identity mismatch');
    echo '通过：响应结构、空账户及账户不匹配的可诊断性' . PHP_EOL;

    // Explicit single-process raw debug: original query including test token,
    // actual HTTP status, headers and raw body are written to a dedicated log.
    $tmpBase = sys_get_temp_dir() . '/tx-probe-raw-test-' . bin2hex(random_bytes(8));
    define('BASE_PATH', $tmpBase);
    putenv('TX_ACCOUNT_PROBE_RAW_LOG=1');
    $secretTest = 'SENSITIVE_TEST_ACCESS_DO_NOT_EXPOSE';
    $debugTx = (new ProbeTx())->setToken($secretTest);
    $debugRaw = response(11012, ['list'=>[]], 'ORIGINAL_REMOTE_MESSAGE');
    $debugTx->http->responses[] = [403, $debugRaw];
    errorOf($debugTx, ProbeError::HTTP_FAILED);
    $rawLog = $tmpBase . '/runtime/logs/tencent-account-probe-raw.log';
    probeCheck(is_file($rawLog), 'opt-in raw log file must be created');
    $logText = file_get_contents($rawLog);
    probeCheck(strpos($logText, $secretTest) !== false
        && strpos($logText, $debugRaw) !== false
        && strpos($logText, 'HTTP_STATUS=403') !== false
        && strpos($logText, 'phase=REQUEST') !== false
        && strpos($logText, 'phase=RESPONSE') !== false,
        'opt-in log must include full request and raw response');
    probeCheck((fileperms($rawLog) & 0777) === 0600,
        'raw log must have local file permissions 0600');
    $debugTx->http->responses[] = new \RuntimeException('ORIGINAL_TRANSPORT_ERROR ' . $secretTest);
    errorOf($debugTx, ProbeError::NETWORK_FAILED);
    $logText = file_get_contents($rawLog);
    probeCheck(strpos($logText, 'ORIGINAL_TRANSPORT_ERROR ' . $secretTest) !== false,
        'raw transport exception must be logged for debugging');
    putenv('TX_ACCOUNT_PROBE_RAW_LOG');
    unlink($rawLog);
    rmdir($tmpBase . '/runtime/logs');
    rmdir($tmpBase . '/runtime');
    rmdir($tmpBase);
    echo '通过：显式原始日志记录真实请求响应和异常，默认关闭' . PHP_EOL;
    echo '共通过 4 组腾讯广告主远程诊断隔离测试。' . PHP_EOL;
}
