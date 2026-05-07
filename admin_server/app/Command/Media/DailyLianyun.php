<?php

declare(strict_types=1);

namespace App\Command\Media;

use App\Model\App;
use App\Model\Media\MediaDailyLianyun;
use App\Model\Media\MediaIncome;
use App\Model\Mkt\ReportDailyLianyun;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class DailyLianyun extends HyperfCommand
{
    protected $name = 'media:dailyLianyun';

    protected function getArguments()
    {
        return [
            ['date', InputArgument::OPTIONAL, '日期', date('Y-m-d', strtotime('-1 days'))]
        ];
    }

    public function handle()
    {
        $args = $this->input->getArguments();
        $date = data_get($args, 'date');

        $dateArr = [
            'today' => date('Y-m-d'),
            'yesterday' => date('Y-m-d', strtotime('-1 days')),
        ];
        $date = $dateArr[$date] ?? $date;

        // 联运应用
        $appLianyun = App::query()
            ->where('ly_appid', '!=', '')
            ->pluck('app_code')
            ->toArray();
        // 最近15日有消耗应用
        $appLast = ReportDailyLianyun::query()
            ->select([
                Db::raw('MAX(date) as max_date'),
                Db::raw('MIN(date) as min_date'),
                'app_code'
            ])
            ->where('cost', '>', 0)
            ->groupBy('app_code')
            ->get()
            ->toArray();
        // 排序
        $appLast = collect($appLast)
            ->where('max_date', '>=', date('Y-m-d', strtotime('-15 days', strtotime($date))))
            ->sortByDesc('min_date')
            ->pluck('app_code')
            ->toArray();
        $appCost = ReportDailyLianyun::query()
            ->where('date', $date)
            ->select([
                Db::raw('SUM(cost) as total'),
                'app_code',
            ])
            ->groupBy('app_code')
            ->get()
            ->toArray();
        $appCost = collect($appCost)->pluck('total', 'app_code')->toArray();
        $appRevenue = MediaIncome::query()
            ->where('date', $date)
            ->select([
                Db::raw('SUM(revenue) as total'),
                'app_code',
            ])
            ->whereIn('app_code', $appLianyun)
            ->groupBy('app_code')
            ->get()
            ->toArray();
        $appRevenue = collect($appRevenue)->pluck('total', 'app_code')->toArray();
        $appList = (new App())->appList([], [], [], [], []);

        $totalCost = array_sum($appCost);
        $mediaRevenue = array_sum($appRevenue);
        $mediaType = MediaIncome::query()
            ->where('date', $date)
            ->groupBy('media_type')
            ->pluck('media_type')
            ->toArray();
        $data = [
            'date' => $date,
            'media_type' => $mediaType,
            'total_cost' => $totalCost,
            'media_revenue' => $mediaRevenue,
            'total_revenue' => $mediaRevenue - $totalCost,
            'detail' => []
        ];

        foreach ($appLast as $appCode) {
            $cost = $appCost[$appCode] ?? 0;
            $revenue = $appRevenue[$appCode] ?? 0;
            $data['detail'][$appCode] = [
                'app_code' => $appCode,
                'app_name' => $appList[$appCode] ?? '',
                'cost' => round($cost, 2),
                'revenue' => round($revenue, 2),
                'roi' => $cost > 0 ? round($revenue / $cost, 2) : 0
            ];
            unset($appRevenue[$appCode]);
        }
        $data['other_revenue'] = round(array_sum($appRevenue), 2);
        MediaDailyLianyun::query()
            ->updateOrCreate([
                'date' => $date
            ], $data);
    }
}
