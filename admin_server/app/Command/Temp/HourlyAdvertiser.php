<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Service\Mkt\MktService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use App\Resource\Launch\LaunchAd\Advertiser;
use App\Service\Launch\AdvertiserService;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class HourlyAdvertiser extends HyperfCommand
{

    protected $name = 'temp:hourly_advertiser';
    protected $advertiserService;

    protected function configure()
    {
        parent::configure();
        $this->setDescription("账户维度");
    }

    public function handle()
    {
        $this->line('Hello HourlyAdvertiser!', 'info');
        $apps = make(MktService::class)->appListPutSelf();
        $this->advertiserService = make(AdvertiserService::class);

        $now_date = date('Y-m-d');
        $now_time = date('Y-m-d H:i:s');
        $hourly = date('Y-m-d H:00:00', time() - 3600);
        foreach ($apps as $app => $name) {
            $params = [
                'app_code' => $app,
                'date_begin' => $now_date,
                'date_end' => $now_date,
            ];
            $this->log(__LINE__, $params);
            $records = $this->advertiserService->advertiser_for_script($params);

            $advertiser_ids = array_column($records, 'advertiser_id');
            $launch_times = $this->launch_time($advertiser_ids);
            $this->log(__LINE__, $advertiser_ids);
            $this->log(__LINE__, $launch_times);
            $history_records = $this->history($advertiser_ids, $hourly);

            foreach ($records as $record) {
                $this->log(__LINE__, $record);
                $history = $history_records[$record['advertiser_id']];
                $install_roi_24h = $this->install_roi_24h($record);

                $data = ['c_time' => $now_time, 'record_time' => $hourly];
                $data['advertiser_id'] = $record['advertiser_id'];
                $data['advertiser_name'] = $record['advertiser_name'];
                $data['app_code'] = $record['app_code'];
                $data['launch_type'] = $record['launch_type'] * 10 + $record['active_type'];
                $data['launch_time'] = $launch_times[$record['advertiser_id']];
                $data['status'] = $record['status'];
                $data['budget'] = $record['budget'];
                $data['cost'] = $record['cost'];
                $data['balance'] = null;
                $data['show'] = $record['show'];
                $data['avg_show_cost'] = $record['avg_show_cost'];
                $data['click'] = $record['click'];
                $data['click_rate'] = $record['click_rate'];
                $data['avg_click_cost'] = $record['avg_click_cost'];
                $data['yesterday_ctr'] = $history['1day']['click_rate'];
                $data['convert'] = $record['convert'];
                $data['convert_cost'] = $record['convert_cost'];
                $data['convert_rate'] = $record['convert_rate'];
                $data['attribution_app_24h_roi'] = $record['attribution_app_24h_roi'];
                $data['attribution_app_24h_roi_1hour'] = $history['1hour']['attribution_app_24h_roi'];
                $data['attribution_app_24h_roi_1day'] = $history['1day']['attribution_app_24h_roi'];
                $data['attribution_app_24h_roi_2day'] = $history['2day']['attribution_app_24h_roi'];
                $data['attribution_app_24h_roi_3day'] = $history['3day']['attribution_app_24h_roi'];
                $data['roi'] = $install_roi_24h;
                $data['health_score'] = $this->health_score($record['attribution_app_24h_roi'], $hourly);

                $this->log(__LINE__, $data);
                Db::table('report_hourly_advertiser')->insert($data);
            }
        }

    }

    public function launch_time($advertiser_ids)
    {
        $list = Db::table("report_daily_advertiser")->whereIn('advertiser_id', $advertiser_ids)
            ->where('cost', '>', '0')
            ->groupBy(['advertiser_id'])
            ->select([Db::raw('MIN(created_time) AS launch_time'), 'advertiser_id'])
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $this->log(__LINE__, $list);
        return array_column($list, 'launch_time', 'advertiser_id');
    }

    public function history($advertiser_ids, $record_time)
    {
        $timestamp = strtotime($record_time);
        $_1hour = date('Y-m-d H:00:00', $timestamp - 3600);
        $_1day = date('Y-m-d H:00:00', $timestamp - 3600 * 24 * 1);
        $_2day = date('Y-m-d H:00:00', $timestamp - 3600 * 24 * 2);
        $_3day = date('Y-m-d H:00:00', $timestamp - 3600 * 24 * 3);

        $list = Db::table("report_hourly_advertiser")
            ->whereIn('advertiser_id', $advertiser_ids)
            ->whereIn('record_time', [$_1hour, $_1day, $_2day, $_3day])
            ->select(['record_time', 'advertiser_id', 'attribution_app_24h_roi', 'click_rate'])
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $result = [];
        foreach ($list as $item) {
            switch ($item['record_time']) {
                case $_1hour:
                    $result[$item['advertiser_id']]['1hour'] = $item;
                    break;
                case $_1day:
                    $result[$item['advertiser_id']]['1day'] = $item;
                    break;
                case $_2day:
                    $result[$item['advertiser_id']]['2day'] = $item;
                    break;
                case $_3day:
                    $result[$item['advertiser_id']]['3day'] = $item;
                    break;
            }
        }
        return $result;
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

    public function install_roi_24h($item)
    {
        return data_get($item, 'cost') <= 0 ? 0 : data_get($item, 'install_revenue24') / data_get($item, 'cost');
    }

    public function log($line, $data)
    {
        $this->line('Notice: ' . $line . ' ' . json_encode($data), 'info');
    }
}
