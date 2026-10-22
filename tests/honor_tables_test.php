<?php
/** 荣耀现有点击表生命周期及旧抓取调度检查，不执行建表。 */
namespace Illuminate\Console { class Command {} }
namespace {
    function ensure($condition, $message) { if (!$condition) { throw new \RuntimeException($message); } }
    require dirname(__DIR__) . '/api_server/app/Console/Commands/Tools/TableMarket.php';
    require dirname(__DIR__) . '/api_server/app/Console/Commands/Tools/TableAdTemp.php';
    foreach ([\App\Console\Commands\Tools\TableMarket::class, \App\Console\Commands\Tools\TableAdTemp::class] as $class) {
        $property = (new \ReflectionClass($class))->getProperty('table'); $property->setAccessible(true);
        $tables = $property->getValue(new $class());
        ensure(in_array('app_%s_honor', $tables, true) || isset($tables['app_%s_honor']), '荣耀点击未接入原清理或归档周期');
    }
    $cron = file_get_contents(dirname(__DIR__) . '/admin_server/config/autoload/crontab.php');
    ensure(strpos($cron, "setName('mkt:honor-today')") === false && strpos($cron, "setName('mkt:honor-yesterday')") === false, '旧荣耀网页抓取定时任务未停用');
    echo '通过：荣耀点击表清理归档登记及旧网页抓取定时任务停用' . PHP_EOL;
    echo '共通过 2 项荣耀生命周期检查。' . PHP_EOL;
}
