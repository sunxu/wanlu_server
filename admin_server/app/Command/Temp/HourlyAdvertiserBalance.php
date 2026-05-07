<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Library\Mkt\MktJl;
use App\Service\Mkt\MktService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class HourlyAdvertiserBalance extends HyperfCommand
{

    protected $name = 'temp:hourly_advertiser_balance';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("账户余额");
    }

    public function handle()
    {
        $this->line('Hello HourlyAdvertiserBalance!', 'info');
        $service = make(MktService::class);


        $list = Db::table("report_hourly_advertiser")
            ->whereNull('balance')
            ->select('advertiser_id')
            ->groupBy(['advertiser_id'])
            ->limit(30)
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        foreach ($list as $item) {
            $fund = $service->getFund($item['advertiser_id']);
            $balance = $fund['balance'] ?: $fund['wallet_total_balance_valid'];
            $this->log(__LINE__, [$item, $balance]);
            Db::table("report_hourly_advertiser")
                ->where('advertiser_id', $item['advertiser_id'])
                ->whereNull('balance')
                ->update(['balance' => $balance]);
        }

        $this->update_health_score("report_hourly_advertiser");
        $this->update_health_score("report_hourly_ad");
    }

    public function update_health_score($table)
    {

        $list = Db::table($table)
            ->where('health_score', -1)
            ->select(['id', 'attribution_app_24h_roi', 'record_time'])
            ->limit(100)
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        foreach ($list as $item) {
            Db::table($table)
                ->where('id', $item['id'])
                ->update(['health_score' => $this->health_score(
                    $item['attribution_app_24h_roi'],
                    $item['record_time'])]);
        }
    }

    public function log($line, $data)
    {
        $this->line('Notice: ' . $line . ' ' . json_encode($data), 'info');
    }

    public function health_score($roi, $hourly)
    {
        $scores = [
            1 => array(0.12, 0.12),
            2 => array(0.39, 0.39),
            3 => array(0.41, 0.41),
            4 => array(0.44, 0.44),
            5 => array(0.32, 0.5),
            6 => array(0.29, 0.52),
            7 => array(0.38, 0.54),
            8 => array(0.36, 0.58),
            9 => array(0.39, 0.56),
            10 => array(0.5, 0.57),
            11 => array(0.49, 0.66),
            12 => array(0.5, 0.68),
            13 => array(0.49, 0.66),
            14 => array(0.56, 0.68),
            15 => array(0.56, 0.68),
            16 => array(0.56, 0.7),
            17 => array(0.51, 0.68),
            18 => array(0.54, 0.74),
            19 => array(0.71, 0.82),
            20 => array(0.76, 0.85),
            21 => array(0.77, 0.89),
            22 => array(0.79, 0.89),
            23 => array(0.85, 0.92),
        ];
        $hour = date("H", strtotime($hourly));
        $score = $scores[intval($hour)];
        if ($roi < $score[0]) {
            return 1;
        } elseif ($roi > $score[1]) {
            return 3;
        }
        return 2;
    }
}
