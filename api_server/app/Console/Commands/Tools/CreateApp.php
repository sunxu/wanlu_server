<?php
// 新建应用
namespace App\Console\Commands\Tools;

use DB;
use Illuminate\Console\Command;

class CreateApp extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'create-app {code} {copy}';

    /**
     * Command description
     * @var string
     */
    protected $description = 'php artisan create-app';

    /**
     * Command Handle
     */
    public function handle()
    {
        $arguments = $this->arguments();
        $code = $arguments['code'];
        $copy = $arguments['copy'];

        if (DB::connection('uc')->table('app')
            ->where('app_code', $code)
            ->value('app_id')) {
            echo "APP:{$code} exits" . PHP_EOL;
            return;
        }

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
                'app_name' => $code,
                'type' => $app['type'],
                'sub_type' => $app['sub_type'],
                'created_time' => time(),
                'updated_time' => time(),
            ]);
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }

//        try {
//            DB::table('app_version')
//                ->insertOrIgnore([
//                    'app_code' => $code,
//                    'version' => '1.0.0',
//                    'audit_status' => '[]',
//                    'create_time' => time()
//                ]);
//        } catch (\Exception $e) {
//            echo $e->getMessage() . PHP_EOL;
//        }

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
