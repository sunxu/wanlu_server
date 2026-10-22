<?php

namespace App\Console\Commands\Tools;

use App\Services\Common\App;
use DB;
use Illuminate\Console\Command;
use Schema;

/** 为当前环境中明确指定或全部注册应用幂等补齐腾讯点击表。 */
class TencentClickTables extends Command
{
    protected $signature = 'mkt:tx-tables {appcode?*} {--all : 处理 uc.app 中全部应用，包含模板和停用应用} {--template= : 统一指定已有点击模板表} {--dry-run : 只检查并输出待执行 SQL，不建表}';
    protected $description = '按现有点击表模板幂等创建腾讯点击分表，不添加字段';
    private $templateValidity = [];

    public function handle()
    {
        $this->templateValidity = [];
        $codes = $this->argument('appcode') ?: [];
        if ((!$codes && !$this->option('all')) || ($codes && $this->option('all'))) {
            $this->error('请指定应用代码或使用 --all，两者不能同时使用');
            return 1;
        }
        $knownCodes = DB::connection('uc')->table('app')->orderBy('app_id')->pluck('app_code')->all();
        $codes = array_unique($this->option('all') ? $knownCodes : $codes);
        $required = ['id', 'account_id', 'aid', 'cid', 'campaign_id', 'imei', 'oaid', 'android_id', 'device_id', 'channel', 'vendor', 'ip', 'ua', 'ts', 'os', 'click_id', 'callback_url', 'create_time', 'update_time'];
        foreach (['active', 'keep', 'action'] as $event) {
            foreach (['time', 'status', 'times'] as $suffix) {
                $required[] = $event . '_' . $suffix;
            }
        }
        $fallbacks = array_unique(array_merge(['app_default_bd'], array_map([App::class, 'tableAdBd'], $knownCodes)));
        $targets = [];
        $skipped = 0;
        $invalid = 0;
        foreach ($codes as $code) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/D', $code)) {
                $this->error('无效的应用标识：' . $code);
                $invalid++;
                continue;
            }
            if (!in_array($code, $knownCodes, true)) {
                $this->error('应用不存在：' . $code);
                $invalid++;
                continue;
            }
            $target = App::tableAdTencent($code);
            if (Schema::hasTable($target)) {
                $this->info('已存在：' . $target);
                $skipped++;
                continue;
            }
            $template = $this->option('template') ?: App::tableAdBd($code);
            if (!$this->option('template') && !$this->validTemplate($template, $required)) {
                foreach ($fallbacks as $fallback) {
                    if ($this->validTemplate($fallback, $required)) {
                        $template = $fallback;
                        break;
                    }
                }
            }
            if (!$this->validTemplate($template, $required)) {
                $this->error('应用 ' . $code . ' 没有完整点击模板，请用 --template 指定包含 click_id 和回传字段的已有表');
                $invalid++;
                continue;
            }
            $targets[$target] = $template;
        }
        if ($invalid) {
            $this->error('校验失败 ' . $invalid . ' 项，未执行任何建表操作');
            return 1;
        }
        // 所有输入先校验完毕，再执行 DDL；不会拷贝模板数据。
        $created = 0;
        $failed = 0;
        foreach ($targets as $target => $template) {
            $sql = 'CREATE TABLE IF NOT EXISTS `' . $target . '` LIKE `' . $template . '`';
            if ($this->option('dry-run')) {
                $this->info($sql . ';');
                continue;
            }
            try {
                DB::statement($sql);
                $this->info('已创建：' . $target . '，模板：' . $template);
                $created++;
            } catch (\Throwable $e) {
                $this->error('创建失败：' . $target . '，' . $e->getMessage());
                $failed++;
            }
        }
        $this->info('汇总：应用 ' . count($codes) . '，已存在 ' . $skipped . '，待创建 ' . count($targets) . '，本次创建 ' . $created . '，失败 ' . $failed);
        return $failed ? 1 : 0;
    }

    private function validTemplate(string $table, array $required): bool
    {
        if (!array_key_exists($table, $this->templateValidity)) {
            $this->templateValidity[$table] = preg_match('/^app_[a-zA-Z0-9_]+$/D', $table)
                && Schema::hasTable($table)
                && !array_diff($required, Schema::getColumnListing($table));
        }
        return (bool)$this->templateValidity[$table];
    }
}
