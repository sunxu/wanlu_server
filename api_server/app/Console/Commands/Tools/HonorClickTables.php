<?php

namespace App\Console\Commands\Tools;

use App\Services\Common\App;
use DB;
use Illuminate\Console\Command;
use Schema;

/** 为存量实际应用补齐荣耀点击表，不依赖默认应用或其他平台的模板表。 */
class HonorClickTables extends Command
{
    protected $signature = 'mkt:honor-tables {appcode?*} {--all : 全部注册实际应用，包含停用应用，跳过default} {--dry-run : 仅校验并输出SQL，不建表}';
    protected $description = '幂等创建缺失的荣耀点击分表，不修改已有表';

    public function handle()
    {
        $codes = $this->argument('appcode') ?: [];
        if ((!$codes && !$this->option('all')) || ($codes && $this->option('all'))) {
            $this->error('请指定应用代码或使用 --all，两者不能同时使用');
            return 1;
        }
        $known = DB::connection('uc')->table('app')->orderBy('app_id')->pluck('app_code')->all();
        $codes = array_values(array_unique($this->option('all') ? array_diff($known, ['default']) : $codes));
        $targets = [];
        $invalid = 0;
        $skipped = 0;
        foreach ($codes as $code) {
            // 为MySQL表名及原归档流程附加的_temp后缀预留长度。
            if (!is_string($code) || !preg_match('/^[a-zA-Z0-9_]{1,49}$/D', $code) || $code === 'default' || !in_array($code, $known, true)) {
                $this->error('无效、未注册或非实际应用的标识：' . (string)$code);
                $invalid++;
                continue;
            }
            $table = App::tableAdHonor($code);
            if (Schema::hasTable($table)) {
                $this->info('已存在，跳过：' . $table);
                $skipped++;
                continue;
            }
            $targets[] = $table;
        }
        // 全部输入校验完成后再执行DDL，避免非法输入导致部分建表。
        if ($invalid) {
            $this->error('预检失败 ' . $invalid . ' 项，未执行任何建表操作');
            return 1;
        }
        $created = 0;
        $failed = 0;
        foreach ($targets as $table) {
            $sql = $this->createSql($table);
            if ($this->option('dry-run')) { $this->info($sql . ';'); continue; }
            try {
                DB::statement($sql);
                $this->info('已创建：' . $table);
                $created++;
            } catch (\Throwable $e) {
                $this->error('创建失败：' . $table . '，' . $e->getMessage());
                $failed++;
            }
        }
        $this->info('汇总：应用 ' . count($codes) . '，已存在 ' . $skipped . '，待创建 ' . count($targets) . '，本次创建 ' . $created . '，失败 ' . $failed);
        return $failed ? 1 : 0;
    }

    private function createSql(string $table): string
    {
        // 沿用当前工程点击表公共字段和索引，补荣耀track_id；不复制任何现有业务数据。
        // callback_url允许空值，因为现有荣耀点击入口不下发此字段。
        return <<<SQL
CREATE TABLE IF NOT EXISTS `{$table}` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` varchar(100) NOT NULL DEFAULT '' COMMENT '广告主ID',
  `imei` varchar(100) NOT NULL DEFAULT '' COMMENT 'IMEI的MD5',
  `oaid` varchar(100) NOT NULL DEFAULT '' COMMENT 'OAID',
  `android_id` varchar(100) NOT NULL DEFAULT '' COMMENT 'ANDROIDID的MD5',
  `device_id` varchar(100) NOT NULL DEFAULT '' COMMENT '归因设备ID',
  `channel` int(10) unsigned NOT NULL DEFAULT '0',
  `vendor` varchar(50) NOT NULL DEFAULT '',
  `ip` varchar(50) NOT NULL DEFAULT '',
  `ua` varchar(255) NOT NULL DEFAULT '',
  `model` varchar(100) NOT NULL DEFAULT '',
  `mac` varchar(50) NOT NULL DEFAULT '',
  `ts` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '点击时间戳',
  `os` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `campaign_id` varchar(100) NOT NULL DEFAULT '',
  `campaign_name` varchar(255) NOT NULL DEFAULT '',
  `aid` varchar(100) NOT NULL DEFAULT '',
  `aid_name` varchar(255) NOT NULL DEFAULT '',
  `cid` varchar(100) NOT NULL DEFAULT '',
  `cid_name` varchar(255) NOT NULL DEFAULT '',
  `csite` varchar(255) NOT NULL DEFAULT '',
  `convert_id` varchar(100) NOT NULL DEFAULT '',
  `request_id` varchar(100) NOT NULL DEFAULT '' COMMENT '请求下发的ID',
  `track_id` varchar(255) NOT NULL DEFAULT '' COMMENT '荣耀回传追踪ID',
  `union_site` varchar(255) NOT NULL DEFAULT '',
  `callback_url` text NULL COMMENT '回调地址',
  `active_time` int(10) unsigned NOT NULL DEFAULT '0',
  `active_status` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `active_times` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `keep_time` int(10) unsigned NOT NULL DEFAULT '0',
  `keep_status` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `keep_times` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `action_time` int(10) unsigned NOT NULL DEFAULT '0',
  `action_status` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `action_times` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `update_time` int(10) unsigned NOT NULL DEFAULT '0',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_imei_deviceid_aid_accountid` (`imei`,`device_id`,`aid`,`account_id`),
  KEY `idx_androidid_deviceid_aid_accountid` (`android_id`,`device_id`,`aid`,`account_id`),
  KEY `idx_oaid_deviceid_aid_accountid` (`oaid`,`device_id`,`aid`,`account_id`),
  KEY `idx_deviceid_ts` (`device_id`,`ts`),
  KEY `idx_active` (`active_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=DYNAMIC COMMENT='归因设备-荣耀'
SQL;
    }
}
