<?php

declare(strict_types=1);

namespace App\Command\Media;

use App\Constants\MediaConst;
use App\Model\Media\MediaDailyCompany;
use App\Model\Media\MediaIncome;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class DailyCompany extends HyperfCommand
{
    protected $name = 'media:dailyCompany';

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

        $companyList = Db::table('app_company')
            ->pluck('company_name', 'company_id')
            ->toArray();
        // 最近15日有消耗应用
        $list = MediaIncome::query()
            ->where('date', $date)
            ->select([
                Db::raw('SUM(revenue) as revenue'),
                'media_type',
                'media_company'
            ])
            ->groupBy('media_type', 'media_company')
            ->orderBy('media_company')
            ->get()
            ->toArray();
        $data = [
            'date' => $date,
            'total_revenue' => 0,
            'detail' => []
        ];
        $mediaType = MediaConst::MEDIA_TYPE;
        foreach ($list as $item) {
            $data['total_revenue'] = round($data['total_revenue'] + $item['revenue'], 2);
            if (!isset($data['detail'][$item['media_company']])) {
                $data['detail'][$item['media_company']] = [
                    'company_id' => $item['media_company'],
                    'company_name' => $companyList[$item['media_company']] ?? '未知',
                    'company_revenue' => 0,
                    'detail' => []
                ];
            }

            $data['detail'][$item['media_company']]['company_revenue'] = round($data['detail'][$item['media_company']]['company_revenue'] + $item['revenue'], 2);
            $data['detail'][$item['media_company']]['detail'][$item['media_type']] = [
                'media_type' => $item['media_type'],
                'media_name' => $mediaType[$item['media_type']] ?? $item['media_type'],
                'revenue' => round($item['revenue'], 2)
            ];
        }
        $data['detail'] = array_values($data['detail']);
        MediaDailyCompany::query()
            ->updateOrCreate([
                'date' => $date
            ], $data);
    }
}
