<?php

declare(strict_types=1);

namespace App\Command\Media;

use App\Model\App;
use App\Model\AppMediaApp;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class AppCode extends HyperfCommand
{
    protected $name = 'media:appCode';

    public function handle()
    {
        $appList = App::query()
            ->where('package_name', '!=', '')
            ->pluck('app_code', 'package_name')
            ->toArray();

        foreach ($appList as $packageName => $appCode) {
            AppMediaApp::query()
                ->where('app_code', '')
                ->where('package_name', '=', trim($packageName))
                ->update(['app_code' => $appCode]);
        }
    }
}
