<?php

namespace App\Console\Commands\Tools;

use App\Constants\AdConst;
use App\Services\Common\App;
use DB;
use Illuminate\Console\Command;
use Schema;

/** 仅初始化明确指定且已绑定腾讯广告主的应用，不自动为所有应用建表。 */
class TencentClickTables extends Command
{
    protected $signature = 'mkt:tx-tables {appcode*} {--template= : 已有包含 click_id 的点击表，默认使用应用自己的百度点击表}';
    protected $description = '按现有点击表模板幂等创建腾讯点击分表，不添加字段';

    public function handle()
    {
        $targets = [];
        foreach ($this->argument('appcode') as $code) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/D', $code)) {
                $this->error('无效的应用标识');
                return 1;
            }
            $app = App::getAppByCode($code);
            $bound = DB::connection('uc')->table('mkt_advertiser')->where('platform', AdConst::PUT_SOURCE_TX)
                ->where('app_code', $code)->where('status', 1)->exists();
            if (!$app || !$bound) {
                $this->error('应用不存在或未绑定启用的腾讯广告主：' . $code);
                return 1;
            }
            $target = App::tableAdTencent($code);
            if (Schema::hasTable($target)) {
                $this->info('已存在：' . $target);
                continue;
            }
            $template = $this->option('template') ?: App::tableAdBd($code);
            if (!preg_match('/^app_[a-zA-Z0-9_]+$/D', $template) || !Schema::hasTable($template)) {
                $this->error('点击模板不存在，请用 --template 指定已有点击表：' . $template);
                return 1;
            }
            $required = ['id', 'account_id', 'aid', 'cid', 'campaign_id', 'imei', 'oaid', 'android_id', 'device_id', 'channel', 'vendor', 'ip', 'ua', 'ts', 'os', 'click_id', 'callback_url', 'create_time', 'update_time'];
            foreach (['active', 'keep', 'action'] as $event) {
                foreach (['time', 'status', 'times'] as $suffix) {
                    $required[] = $event . '_' . $suffix;
                }
            }
            if (array_diff($required, Schema::getColumnListing($template))) {
                $this->error('点击模板缺少归因或回传所需的现有字段：' . $template);
                return 1;
            }
            $targets[$target] = $template;
        }
        // 所有输入先校验完毕，再执行 DDL；不会拷贝模板数据。
        foreach ($targets as $target => $template) {
            DB::statement('CREATE TABLE IF NOT EXISTS `' . $target . '` LIKE `' . $template . '`');
            $this->info('已创建：' . $target);
        }
        return 0;
    }
}
