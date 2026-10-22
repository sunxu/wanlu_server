<?php
/** 执行真实补表命令和荣耀点击入口；仅用内存替代数据库，不执行真实DDL。 */
namespace HonorTablesTest {
    class Store {
        public static $apps = [];
        public static $tables = [];
        public static $statements = [];
        public static $failTable;
        public static function reset() {
            self::$apps = [
                ['app_code' => 'default', 'status' => 1], ['app_code' => 'online', 'status' => 1],
                ['app_code' => 'stopped', 'status' => 0], ['app_code' => 'existing', 'status' => 1],
            ];
            self::$tables = [
                'app_existing_honor' => ['columns' => ['keep' => 'varchar(10)'], 'rows' => [['keep' => 'original']], 'indexes' => ['keep-index']],
                'app_online_ad' => ['columns' => ['id' => 'bigint'], 'rows' => [['id' => 7]], 'indexes' => ['original-index']],
            ];
            self::$statements = []; self::$failTable = null;
        }
    }
    class Query {
        private $table;
        public function __construct($table) { $this->table = $table; }
        public function orderBy($field) { return $this; }
        public function pluck($field) {
            if ($this->table !== 'uc.app') { throw new \RuntimeException('只允许读取当前环境应用列表'); }
            return new class(array_column(Store::$apps, $field)) {
                private $items;
                public function __construct($items) { $this->items = $items; }
                public function all() { return $this->items; }
            };
        }
        public function insert($row) {
            if (!isset(Store::$tables[$this->table])) { throw new \RuntimeException('点击表不存在'); }
            $columns = Store::$tables[$this->table]['columns'];
            foreach ($row as $name => $value) {
                if (!array_key_exists($name, $columns)) { throw new \RuntimeException('插入未知字段：' . $name); }
            }
            foreach ($columns as $name => $definition) {
                if (!array_key_exists($name, $row) && stripos($definition, 'NOT NULL') !== false &&
                    stripos($definition, 'DEFAULT') === false && stripos($definition, 'AUTO_INCREMENT') === false) {
                    throw new \RuntimeException('点击入口未提供无默认值的必填字段：' . $name);
                }
            }
            Store::$tables[$this->table]['rows'][] = $row;
            return true;
        }
    }
    class Connection {
        public function table($table) { return new Query('uc.' . $table); }
    }
}
namespace Illuminate\Console {
    class Command {
        public $codes = [];
        public $options = [];
        public $messages = [];
        public function argument($name) { return $this->codes; }
        public function option($name) { return $this->options[$name] ?? false; }
        public function info($message) { $this->messages[] = $message; }
        public function error($message) { $this->messages[] = $message; }
    }
}
namespace Illuminate\Http {
    class Request {
        private $params;
        public function __construct($params) { $this->params = $params; }
        public function input() { return $this->params; }
    }
}
namespace Laravel\Lumen\Routing { class Controller {} }
namespace App\Services\Common {
    class App {
        public static function tableAdHonor(string $code): string { return 'app_' . $code . '_honor'; }
        public static function getAppByCode($code) {
            foreach (\HonorTablesTest\Store::$apps as $app) { if ($app['app_code'] === $code) { return $app; } }
            return [];
        }
    }
}
namespace {
    use HonorTablesTest\Store;
    class Schema { public static function hasTable($table) { return isset(Store::$tables[$table]); } }
    class DB {
        public static function connection($name) {
            if ($name !== 'uc') { throw new \RuntimeException('不能连接其他环境'); }
            return new \HonorTablesTest\Connection();
        }
        public static function table($table) { return new \HonorTablesTest\Query($table); }
        public static function statement($sql) {
            if (!preg_match('/^CREATE TABLE IF NOT EXISTS `(app_[A-Za-z0-9_]+_honor)` \(/', $sql, $match)) {
                throw new \RuntimeException('只允许直接创建荣耀应用表');
            }
            Store::$statements[] = $sql;
            $table = $match[1];
            if (Store::$failTable === $table) { throw new \RuntimeException('模拟单表DDL失败'); }
            if (isset(Store::$tables[$table])) { return true; }
            preg_match_all('/^  `([^`]+)` (.+),$/m', $sql, $fields, PREG_SET_ORDER);
            $columns = [];
            foreach ($fields as $field) { $columns[$field[1]] = $field[2]; }
            preg_match_all('/^  (?:PRIMARY KEY|KEY `[^`]+`) \([^\n]+/m', $sql, $indexes);
            Store::$tables[$table] = ['columns' => $columns, 'rows' => [], 'indexes' => $indexes[0]];
            return true;
        }
    }
    function ensure($condition, $message) { if (!$condition) { throw new \RuntimeException($message); } }
    require dirname(__DIR__) . '/api_server/app/Console/Commands/Tools/HonorClickTables.php';
    require dirname(__DIR__) . '/api_server/app/Http/Controllers/V1/AdController.php';
    $run = function ($options = [], $codes = []) {
        $command = new \App\Console\Commands\Tools\HonorClickTables();
        $command->codes = $codes; $command->options = $options;
        return [$command->handle(), $command];
    };

    Store::reset(); $before = Store::$tables;
    [$status, $command] = $run(['all' => true, 'dry-run' => true]);
    ensure($status === 0 && !Store::$statements && Store::$tables === $before, '预览不应建表或修改数据');
    $preview = implode('\n', $command->messages);
    ensure(strpos($preview, 'app_online_honor') !== false && strpos($preview, 'app_stopped_honor') !== false, '预览必须包含停用和未绑定应用');
    ensure(strpos($preview, 'app_default_') === false && strpos($preview, ' LIKE ') === false, '不得创建或依赖默认模板');
    ensure($run(['all' => true])[0] === 0, '没有任何荣耀模板也应能补齐存量表');
    foreach (['online', 'stopped'] as $code) {
        ensure(isset(Store::$tables['app_' . $code . '_honor']) && !Store::$tables['app_' . $code . '_honor']['rows'], '应直接创建空的荣耀分表');
    }
    ensure(array_intersect_key(Store::$tables, $before) === $before, '已有荣耀表及其他平台表必须保持原样');
    $statements = Store::$statements;
    ensure($run(['all' => true])[0] === 0 && Store::$statements === $statements, '重复运行不得重建已有表');
    echo '通过：无模板全量补表、预览、停用应用覆盖、跳过default、保留已有表及重复执行' . PHP_EOL;

    $schema = Store::$tables['app_online_honor'];
    foreach (['id', 'account_id', 'oaid', 'ip', 'ts', 'os', 'request_id', 'track_id', 'imei', 'android_id', 'device_id', 'channel', 'vendor', 'aid', 'cid', 'campaign_id', 'create_time', 'update_time'] as $field) {
        ensure(isset($schema['columns'][$field]), '缺少公共归因字段：' . $field);
    }
    foreach (['active', 'keep', 'action'] as $event) {
        foreach (['time', 'status', 'times'] as $suffix) { ensure(isset($schema['columns'][$event . '_' . $suffix]), '缺少回传状态字段'); }
    }
    ensure(count($schema['indexes']) === 6, '缺少公共点击匹配索引');
    $controller = new \App\Http\Controllers\V1\AdController();
    $result = $controller->honor(new \Illuminate\Http\Request(['account_id' => '123', 'oaid' => 'device-oaid', 'ip' => '127.0.0.1', 'os' => 0, 'request_id' => 'request', 'track_id' => 'track']), 'online');
    ensure($result === 'SUCCESS' && Store::$tables['app_online_honor']['rows'][0]['track_id'] === 'track', '真实荣耀点击入口无法使用新表结构');
    echo '通过：公共字段、事件回传状态、匹配索引及真实荣耀点击入口兼容' . PHP_EOL;

    Store::reset();
    ensure($run([], ['online', 'online'])[0] === 0 && count(Store::$statements) === 1 && !isset(Store::$tables['app_stopped_honor']), '指定应用应去重且不影响其他应用');
    Store::reset();
    foreach ([[[], []], [['all' => true], ['online']], [[], ['unknown']], [[], ['default']], [[], ['online', 'invalid-code']], [[], [str_repeat('x', 50)]]] as [$options, $codes]) {
        ensure($run($options, $codes)[0] === 1 && !Store::$statements, '参数或应用校验失败不能执行DDL');
    }
    Store::$apps[] = ['app_code' => 'invalid-code'];
    ensure($run(['all' => true])[0] === 1 && !Store::$statements, '全量存在非法应用时必须先整体预检失败');
    echo '通过：指定应用、去重、非法参数、未注册应用及全量预检失败' . PHP_EOL;

    Store::reset(); Store::$failTable = 'app_online_honor';
    [$status, $command] = $run(['all' => true]);
    ensure($status === 1 && !isset(Store::$tables['app_online_honor']) && isset(Store::$tables['app_stopped_honor']), '单表失败应继续其余目标并返回失败');
    ensure(strpos(implode('\n', $command->messages), '失败 1') !== false, '应明确汇总失败数量');
    Store::$failTable = null;
    ensure($run(['all' => true])[0] === 0 && isset(Store::$tables['app_online_honor']), '重跑应补齐失败表');
    $kernel = file_get_contents(dirname(__DIR__) . '/api_server/app/Console/Kernel.php');
    ensure(strpos($kernel, 'Commands\\Tools\\HonorClickTables::class') !== false, '补表命令必须注册到现有Artisan入口');
    echo '通过：DDL失败统计、继续执行、幂等重跑及命令注册' . PHP_EOL;
    echo '共通过 4 组荣耀存量补表测试。' . PHP_EOL;
}
