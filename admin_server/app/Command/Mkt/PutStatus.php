<?php
// 投放状态监控
declare(strict_types=1);

namespace App\Command\Mkt;

use App\Model\App;
use App\Model\Mkt\ReportDailyAdvertiser;
use App\Service\AppService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;

/**
 * @Command
 */
class PutStatus extends HyperfCommand
{
    protected $name = 'mkt:putStatus';

    public function handle()
    {
        $startDate = date('Y-m-d', strtotime('-2 day'));
        $endDate = date('Y-m-d');

        $appList = $this->getAppList();

        foreach ($appList as $appCode => $adStatus) {
            $putStatus = $this->putStatus($appCode, $startDate, $endDate);

            $service = make(AppService::class);
            if ($adStatus && !$putStatus) {
                $service->editAdStatus($appCode, 0);
            } elseif (!$adStatus && $putStatus) {
                $service->editAdStatus($appCode, 1);
            }
        }
    }

    private function getAppList()
    {
        return App::query()->pluck('ad_status', 'app_code')->toArray();
    }

    private function putStatus(string $appCode, string $startDate, string $endDate)
    {
        return ReportDailyAdvertiser::query()
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->where('cost', '>', 0)
            ->where('app_code', $appCode)
            ->exists();
    }
}
