<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\ModelTools\App;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class SyncApp extends HyperfCommand
{
    protected $name = 'temp:syncapp';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:同步app");
    }

    public function handle()
    {
        $apps = App::get()->all();
        $a = Db::table('app')->orderBy('app_id', 'desc')->first();
        $appId = data_get($a, 'app_id');
        foreach ($apps as $app){
            if(data_get($app, 'appcode') == 'pssvideo' || data_get($app, 'appcode') == 'kyvideo'){
                continue;
            }
            $appId++;
            Db::table('app')->insert([
                'app_id' => $appId,
                'app_code' => data_get($app, 'appcode'),
                'app_name' => data_get($app, 'appname'),
                'abbr' => 'android',
                'sign_secret_key' => '',
                'params_secret_key' => '',
                'status' => 1,
                'type' => data_get($app, 'type'),
                'sub_type' => data_get($app, 'sub_type'),
                'created_time' => time(),
                'updated_time' => time(),
                'extra' => json_encode(['appInfo' => $app]),
            ]);
        }

    }
}