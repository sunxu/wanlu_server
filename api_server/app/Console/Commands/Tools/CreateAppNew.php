<?php
// 新建应用
namespace App\Console\Commands\Tools;

use DB;
use Illuminate\Console\Command;
use Overtrue\Pinyin\Pinyin;

class CreateAppNew extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'create-app-new {copy} {name} {package}';

    /**
     * Command description
     * @var string
     */
    protected $description = 'php artisan create-app-new';

    /**
     * Command Handle
     */
    public function handle()
    {
        $arguments = $this->arguments();
        $copy = $arguments['copy'];
        $name = $arguments['name'];
        $package = $arguments['package'];

        $pinyin = new Pinyin();
        $baseCode = strtolower($pinyin->abbr($name));
        $code = $baseCode;
        $suffix = 'a';
        while (DB::connection('uc')->table('app')
            ->where('app_code', $code)
            ->value('app_id')) {
            $code = $baseCode . $suffix;
            $suffix++;
        }
        echo "APP CODE: {$code}" . PHP_EOL;

        $app = DB::connection('uc')->table('app')
            ->where('app_code', $copy)
            ->first();
        if (empty($app)) {
            echo "invalid APP:{$copy} " . PHP_EOL;
            return;
        }

        // 创建应用
        try {
            DB::connection('uc')->table('app')->insertOrIgnore([
                'sign_secret_key' => substr(md5(uniqid() . time()), 0, 16),
                'params_secret_key' => md5(uniqid() . time()),
                'app_code' => $code,
                'app_name' => $name,
                'ad_status' => 1,
                'package_name' => $package,
                'company_id' => 122,
                'type' => $app['type'],
                'sub_type' => $app['sub_type'],
                'created_time' => time(),
                'updated_time' => time(),
            ]);
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }

        // 复制 app_auth 权限数据
        try {
            $newAppId = DB::connection('uc')->table('app')
                ->where('app_code', $code)->value('app_id');
            $copyAppId = DB::connection('uc')->table('app')
                ->where('app_code', $copy)->value('app_id');
            $copyAuth = DB::connection('uc')->table('app_auth')
                ->where('app_id', $copyAppId)->first();

            if ($newAppId && $copyAuth) {
                DB::connection('uc')->table('app_auth')->insertOrIgnore([
                    'app_id' => $newAppId,
                    'pages' => $copyAuth['pages'],
                    'apis' => $copyAuth['apis'],
                    'created_time' => time(),
                    'updated_time' => time(),
                ]);
                echo "app_auth created for app_id: {$newAppId}" . PHP_EOL;
            }
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }

        // 复制 ad_strategy 策略数据
        try {
            $copyAppName = $app['app_name'];
            $strategies = DB::connection('uc')->table('ad_strategy')
                ->where('app_code', $copy)
                ->where('deleted_time', 0)
                ->get();

            foreach ($strategies as $strategy) {
                $strategy = (array) $strategy;
                $strategyName = str_replace($copyAppName, $name, $strategy['name']);
                unset($strategy['id']);
                $strategy['app_code'] = $code;
                $strategy['name'] = $strategyName;
                $strategy['placement'] = '{}';
                $strategy['created_time'] = time();
                $strategy['updated_time'] = time();
                DB::connection('uc')->table('ad_strategy')->insert($strategy);
            }
            echo "ad_strategy copied: " . count($strategies) . " records" . PHP_EOL;
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }

        // 创建应用表
        $data = DB::select('show tables');
        foreach ($data as $v) {
            foreach ($v as $v2) {
                if (strstr($v2, "app_{$copy}") && !strstr($v2, "2022")) {
                    $table = str_replace("{$copy}", $code, $v2);
                    DB::statement("
                        CREATE TABLE IF NOT EXISTS {$table} LIKE {$v2}
                    ");
                    echo $table . PHP_EOL;
                }
            }
        }
    }

}
