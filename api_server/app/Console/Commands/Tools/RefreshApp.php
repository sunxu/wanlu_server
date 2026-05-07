<?php
// 新建应用
namespace App\Console\Commands\Tools;

use App\Services\Common\App;
use Cache;
use DB;
use Illuminate\Console\Command;

class RefreshApp extends Command
{
    /**
     * Command Name
     * @var string
     */
    protected $signature = 'refresh-app';

    /**
     * Command description
     * @var string
     */
    protected $description = 'php artisan refresh-app';

    /**
     * Command Handle
     */
    public function handle()
    {
        // 应用信息
        $appList = App::appInfoList();
        
        foreach ($appList as $app) {
            // 避免缓存时间同时失效造成服务器压力巨大
            Cache::put($this->getIdKey($app['appid']), $app, $this->getMinutes() * 60);
            Cache::put($this->getCodeKey($app['appcode']), $app, $this->getMinutes() * 60);
        }
    }

    protected function getIdKey(string $appId)
    {
        return App::getAppCacheIdKey($appId);
    }

    protected function getCodeKey(string $appCode)
    {
        return App::getAppCacheCodeKey($appCode);
    }

    protected function getMinutes()
    {
        return mt_rand(60, 24 * 60);
    }
}
