<?php
/** 应用创建建表测试：运行真实创建入口，使用内存数据库，不执行实际 DDL。 */
namespace TencentAppTest {
    class Store
    {
        public static $tables = [];
        public static $rows = [];
        public static $statements = [];
        public static $failTable;
        public static function reset($withTencent, $withBaidu = true) {
            self::$rows = ['uc.app' => [['app_id' => 1, 'app_code' => 'default', 'app_name' => '模板', 'type' => 1000, 'sub_type' => 1001]]];
            self::$tables = [];
            self::$statements = [];
            self::$failTable = null;
            foreach (['', '_ad', '_ks'] as $suffix) {
                self::$tables['app_default' . $suffix] = ['columns' => ['id', 'device_id'], 'indexes' => ['PRIMARY'], 'data' => [['id' => 1]]];
            }
            if ($withBaidu) {
                $columns = ['id', 'account_id', 'aid', 'cid', 'campaign_id', 'imei', 'oaid', 'android_id', 'device_id', 'channel', 'vendor', 'ip', 'ua', 'ts', 'os', 'click_id', 'callback_url', 'create_time', 'update_time'];
                foreach (['active', 'keep', 'action'] as $event) {
                    foreach (['time', 'status', 'times'] as $suffix) { $columns[] = $event . '_' . $suffix; }
                }
                self::$tables['app_default_bd'] = ['columns' => $columns, 'indexes' => ['PRIMARY', 'idx_device'], 'data' => [['id' => 2]]];
            }
            if ($withTencent) {
                self::$tables['app_default_tx'] = ['columns' => ['id', 'click_id', 'device_id', 'active_status'], 'indexes' => ['PRIMARY', 'idx_tx'], 'data' => [['id' => 3]]];
            }
        }
    }
    class Collection {
        private $items;
        public function __construct($items) { $this->items = $items; }
        public function all() { return $this->items; }
    }
    class Query
    {
        private $key;
        private $where = [];
        public function __construct($key) { $this->key = $key; }
        public function where($key, $value) { $this->where[$key] = $value; return $this; }
        public function first() {
            foreach (Store::$rows[$this->key] ?? [] as $row) {
                $matches = true;
                foreach ($this->where as $key => $value) { if (($row[$key] ?? null) != $value) { $matches = false; } }
                if ($matches) { return $row; }
            }
            return null;
        }
        public function value($key) { return $this->first()[$key] ?? null; }
        public function exists() { return $this->first() !== null; }
        public function orderBy($key) { return $this; }
        public function pluck($key) { return new Collection(array_column(Store::$rows[$this->key] ?? [], $key)); }
        public function get() { return []; }
        public function insertOrIgnore($data) {
            if ($this->key === 'uc.app') { $data['app_id'] = count(Store::$rows['uc.app']) + 1; }
            Store::$rows[$this->key][] = $data;
            return true;
        }
        public function insert($data) { return $this->insertOrIgnore($data); }
    }
    class ModelQuery extends Query {
        public function first() { $row = parent::first(); return $row ? new \App\Model\App($row) : null; }
        public function value($key) { $row = parent::first(); return $row[$key] ?? null; }
    }
    class Connection
    {
        private $name;
        public function __construct($name) { $this->name = $name; }
        public function table($name) { return new Query($this->name . '.' . $name); }
        public function select($sql) {
            if (strtolower(trim($sql)) !== 'show tables') { throw new \RuntimeException('非预期查询'); }
            return array_map(function ($name) { return ['table' => $name]; }, array_keys(Store::$tables));
        }
        public function statement($sql) {
            Store::$statements[] = $sql;
            if (!preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?\s+LIKE\s+`?(\w+)`?/i', $sql, $match)) {
                throw new \RuntimeException('只允许复制已有表结构');
            }
            if (Store::$failTable === $match[1]) { throw new \RuntimeException('模拟 DDL 失败'); }
            if (!isset(Store::$tables[$match[1]])) {
                if (!isset(Store::$tables[$match[2]])) { throw new \RuntimeException('模板不存在'); }
                Store::$tables[$match[1]] = array_replace(Store::$tables[$match[2]], ['data' => []]);
            }
            return true;
        }
    }
}
namespace App\Model {
    class App
    {
        public function __construct($data = []) { $this->fill($data); }
        public static function query() { return new \TencentAppTest\ModelQuery('uc.app'); }
        public function fill($data) { foreach ($data as $key => $value) { $this->$key = $value; } return $this; }
        public function save() { return (new \TencentAppTest\Query('uc.app'))->insertOrIgnore((array)$this); }
    }
}
namespace Hyperf\DbConnection {
    class Db
    {
        public static function connection($name) { return new \TencentAppTest\Connection($name); }
        public static function beginTransaction() {}
        public static function commit() {}
        public static function rollBack() {}
    }
}
namespace Illuminate\Console {
    class Command {
        public $params = [];
        public $options = [];
        public $messages = [];
        public function arguments() { return $this->params; }
        public function argument($key) { return $this->params[$key] ?? null; }
        public function option($key) { return $this->options[$key] ?? null; }
        public function info($message) { $this->messages[] = $message; }
        public function error($message) { $this->messages[] = $message; }
    }
}
namespace Overtrue\Pinyin {
    class Pinyin { public function abbr($name) { return 'newapp'; } }
}
namespace {
    class DB
    {
        public static function connection($name) { return new \TencentAppTest\Connection($name); }
        public static function select($sql) { return self::connection('tools')->select($sql); }
        public static function statement($sql) { return self::connection('tools')->statement($sql); }
    }
    class Schema {
        public static function hasTable($name) { return isset(\TencentAppTest\Store::$tables[$name]); }
        public static function getColumnListing($name) { return \TencentAppTest\Store::$tables[$name]['columns']; }
    }
    class Cache {
        public static function get($key) {
            foreach (\TencentAppTest\Store::$rows['uc.app'] as $app) { if ($key === 'appcode:' . $app['app_code']) { return $app; } }
            return null;
        }
    }
    function data_get($data, $key, $default = null) { return is_object($data) ? ($data->$key ?? $default) : ($data[$key] ?? $default); }
    function ensure($condition, $message) { if (!$condition) { throw new \RuntimeException($message); } }

    $root = dirname(__DIR__);
    require $root . '/admin_server/app/Service/AppService.php';
    require $root . '/api_server/app/Services/Common/App.php';
    require $root . '/api_server/app/Console/Commands/Tools/CreateApp.php';
    require $root . '/api_server/app/Console/Commands/Tools/CreateAppNew.php';
    require $root . '/api_server/app/Constants/AdConst.php';
    require $root . '/api_server/app/Console/Commands/Tools/TencentClickTables.php';

    $initTemplate = function ($templateApp = 'default') {
        $command = new \App\Console\Commands\Tools\TencentClickTables();
        $command->params = ['appcode' => [$templateApp]];
        return $command->handle();
    };

    $create = function ($entry, $copy = 'default') {
        if ($entry === 'admin') {
            (new \App\Service\AppService())->saveData(['app_code' => 'newapp', 'app_name' => '新应用']);
            return;
        }
        $command = $entry === 'create-app' ? new \App\Console\Commands\Tools\CreateApp() : new \App\Console\Commands\Tools\CreateAppNew();
        $command->params = ['code' => 'newapp', 'copy' => $copy, 'name' => '新应用', 'package' => 'com.example.newapp'];
        ob_start();
        try { $command->handle(); } finally { ob_end_clean(); }
    };
    foreach (['admin', 'create-app', 'create-app-new'] as $entry) {
        foreach ([false, true] as $withTencent) {
            \TencentAppTest\Store::reset($withTencent);
            ensure($initTemplate() === 0, '模板初始化失败');
            $original = \TencentAppTest\Store::$tables;
            $expected = $original['app_default_tx'];
            $create($entry);
            ensure(isset(\TencentAppTest\Store::$tables['app_newapp_tx']), $entry . ' 没有自动创建腾讯表');
            ensure(\TencentAppTest\Store::$tables['app_newapp_tx'] === array_replace($expected, ['data' => []]), '必须复制模板的完整字段和索引，不能复制数据');
            ensure(isset(\TencentAppTest\Store::$tables['app_newapp_ad'], \TencentAppTest\Store::$tables['app_newapp_ks']), '原有平台建表流程被改变');
            foreach ($original as $table => $schema) { ensure(\TencentAppTest\Store::$tables[$table] === $schema, '不应修改模板表'); }
            $before = \TencentAppTest\Store::$statements;
            ensure($initTemplate() === 0, '重复初始化应成功');
            ensure(\TencentAppTest\Store::$statements === $before, '已存在的腾讯表不应重复执行 DDL');
        }
        \TencentAppTest\Store::reset(false, false);
        ensure($initTemplate() === 1 && !\TencentAppTest\Store::$statements, '缺少来源表时初始化应失败且不执行 DDL');
        \TencentAppTest\Store::reset(false);
        ensure($initTemplate('default') === 0, '未绑定广告主的应用和模板也应能补表');
        if ($entry !== 'admin') {
            \TencentAppTest\Store::$rows['uc.app'][] = ['app_id' => 2, 'app_code' => 'copyapp', 'app_name' => '来源应用', 'type' => 1000, 'sub_type' => 1001];
            foreach (\TencentAppTest\Store::$tables as $table => $schema) {
                \TencentAppTest\Store::$tables[str_replace('default', 'copyapp', $table)] = $schema;
            }
            ensure($initTemplate('copyapp') === 0, '非 default 来源应用应能初始化模板');
            $create($entry, 'copyapp');
            ensure(\TencentAppTest\Store::$tables['app_newapp_tx'] === \TencentAppTest\Store::$tables['app_copyapp_tx'], '应复制命令指定的来源模板');
        }
        echo '通过：' . $entry . ' 统一复制已初始化模板、保留字段索引、原表不变及幂等' . PHP_EOL;
    }
    $runBulk = function ($options, $codes = []) {
        $command = new \App\Console\Commands\Tools\TencentClickTables();
        $command->params = ['appcode' => $codes];
        $command->options = $options;
        return [$command->handle(), $command];
    };
    $setupBulk = function () {
        \TencentAppTest\Store::reset(false);
        foreach (['online', 'stopped', 'existing'] as $index => $code) {
            \TencentAppTest\Store::$rows['uc.app'][] = ['app_id' => $index + 2, 'app_code' => $code, 'status' => $code === 'stopped' ? 0 : 1];
        }
        \TencentAppTest\Store::$tables['app_online_bd'] = array_replace(\TencentAppTest\Store::$tables['app_default_bd'], ['indexes' => ['PRIMARY', 'idx_online']]);
        \TencentAppTest\Store::$tables['app_existing_tx'] = ['columns' => ['keep'], 'indexes' => ['keep-index'], 'data' => [['keep' => 'existing-data']]];
    };
    $setupBulk();
    $before = \TencentAppTest\Store::$tables;
    [$result, $command] = $runBulk(['all' => true, 'dry-run' => true]);
    ensure($result === 0 && !\TencentAppTest\Store::$statements && \TencentAppTest\Store::$tables === $before, '预览不能执行 DDL 或修改数据');
    ensure(strpos(implode('\n', $command->messages), 'app_stopped_tx') !== false, '全量模式必须覆盖停用应用');
    [$result, $command] = $runBulk(['all' => true]);
    ensure($result === 0, '全量建表应成功');
    foreach (['default', 'online', 'stopped'] as $code) {
        ensure(\TencentAppTest\Store::$tables['app_' . $code . '_tx']['data'] === [], '新表不能复制源数据');
    }
    ensure(\TencentAppTest\Store::$tables['app_online_tx']['indexes'] === ['PRIMARY', 'idx_online'], '应优先复制同应用百度表');
    ensure(\TencentAppTest\Store::$tables['app_existing_tx'] === $before['app_existing_tx'], '已存在表的结构及数据必须保持不变');
    ensure(array_intersect_key(\TencentAppTest\Store::$tables, $before) === $before, '全量补表不得改变任何已有表');
    $statements = \TencentAppTest\Store::$statements;
    ensure($runBulk(['all' => true])[0] === 0 && \TencentAppTest\Store::$statements === $statements, '全量脚本重复执行不得重建已有表');
    $create('admin');
    ensure(isset(\TencentAppTest\Store::$tables['app_newapp_tx']), '全量初始化后新应用应沿用原流程自动复制腾讯表');
    echo '通过：全量覆盖模板、停用及未绑定应用，预览、保留已有表、重复执行和后续新建' . PHP_EOL;

    $validTemplate = $before['app_online_bd'];
    \TencentAppTest\Store::reset(false, false);
    \TencentAppTest\Store::$rows['uc.app'][] = ['app_id' => 2, 'app_code' => 'online'];
    \TencentAppTest\Store::$tables['app_online_bd'] = $validTemplate;
    ensure($runBulk(['all' => true])[0] === 0 && \TencentAppTest\Store::$tables['app_default_tx']['indexes'] === $validTemplate['indexes'], '缺默认模板时应复用当前环境有效点击模板');
    $setupBulk();
    ensure($runBulk(['all' => true, 'template' => 'app_default_bd'])[0] === 0 && \TencentAppTest\Store::$tables['app_online_tx']['indexes'] === $before['app_default_bd']['indexes'], '显式模板应统一应用于所有新表');
    echo '通过：同应用及公共模板回退、显式指定模板' . PHP_EOL;

    $setupBulk();
    \TencentAppTest\Store::$rows['uc.app'][] = ['app_id' => 9, 'app_code' => 'invalid-code'];
    ensure($runBulk(['all' => true])[0] === 1 && !\TencentAppTest\Store::$statements, '任一应用校验失败时不得执行部分 DDL');
    \TencentAppTest\Store::reset(false, false);
    ensure($runBulk(['all' => true])[0] === 1 && !\TencentAppTest\Store::$statements, '无可用模板时必须失败');
    $setupBulk();
    ensure($runBulk(['all' => true, 'template' => 'app_existing_tx'])[0] === 1 && !\TencentAppTest\Store::$statements, '缺失必需字段的模板必须拒绝');
    ensure($runBulk([])[0] === 1 && $runBulk(['all' => true], ['default'])[0] === 1, '必须明确选择全量或指定应用');
    echo '通过：应用、模板和参数预检失败均不执行建表' . PHP_EOL;

    $setupBulk();
    \TencentAppTest\Store::$failTable = 'app_online_tx';
    [$result, $command] = $runBulk(['all' => true]);
    ensure($result === 1 && isset(\TencentAppTest\Store::$tables['app_default_tx'], \TencentAppTest\Store::$tables['app_stopped_tx']), '单表 DDL 失败应继续处理其他应用并返回失败');
    ensure(strpos(implode('\n', $command->messages), '失败 1') !== false, '应输出失败汇总');
    \TencentAppTest\Store::$failTable = null;
    ensure($runBulk(['all' => true])[0] === 0 && isset(\TencentAppTest\Store::$tables['app_online_tx']), '重复运行应补齐上次失败的表');
    echo '通过：执行失败统计及幂等重跑补齐' . PHP_EOL;
    echo '共通过 7 组应用建表及全量初始化测试。' . PHP_EOL;
}
