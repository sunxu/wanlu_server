<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Library\Aliyun\Sls;
use App\Service\Placement\LaunchBaseService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class MusicDataCommand extends HyperfCommand
{
    protected $name = 'temp:musicData';

    protected $appCode = 'ammusic';

    protected $days = ['2022-08-20', '2022-08-21'];

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:猜歌数据");
    }

    public function handle()
    {
        $this->info('Start');
        $func = function ($day) {
            $data = Db::connection('mis')->table('user_ammusic_account')->select(['user_id', 'withdrawal_amount_success'])
                ->whereBetween('created_time', [strtotime($day), strtotime($day) + 60 * 60 * 24 -1])
                ->get()->all();
            $slsData = $this->slsQuery($day);
            $data = collect($data)->map(function ($item) use ($slsData, $day) {
                $userId = data_get($item, 'user_id');
                $userEcpm = data_get($slsData, $userId);
                $ecpmSum = data_get($userEcpm, 'ecpm_sum', 0);
                $ecpmAvg = data_get($userEcpm, 'ecpm_avg', 0);
                $ecpmMax = floatval(trim(data_get($userEcpm, 'ecpm_max', '[0]'), '[]'));
                $ecpmMin = floatval(trim(data_get($userEcpm, 'ecpm_min', '[0]'), '[]'));
                return [
                    'day' => $day,
                    'user_id' => $userId,
                    'gain_amount' => round(data_get($item, 'withdrawal_amount_success', 0) / 100, 2),
                    'ecpm_sum' => round($ecpmSum, 2),
                    'ecpm_max' => round($ecpmMax, 2),
                    'ecpm_min' => round($ecpmMin, 2),
                    'ecpm_avg' => round($ecpmAvg, 2),
                    'income' => round($ecpmSum / 1000, 2),
                ];
            })->all();
            Db::table('temp_music_data')->insert($data);
        };
        array_map_safe($func, $this->days);
        $this->info("Done");
    }

    protected function slsQuery($day)
    {
        $query = " __topic__:{$this->appCode} and event_type:ad_show | select user_id, sum(ecpm) as \"ecpm_sum\"," .
            " max(ecpm,1) as \"ecpm_max\", min(ecpm,1) as \"ecpm_min\", avg(ecpm) as \"ecpm_avg\" FROM log group by user_id limit 10000";


        $Sls = make(Sls::class);
        $Sls->setLogstore($Sls->AliyunConf['sls_logstore_ad_event']);
        $Sls->setTopic($this->appCode);
        $res = $Sls->getLogsWithSql(strtotime($day), strtotime($day) + 60 * 60 * 24 - 1, $query);
        return collect($res['log_list'])->pluck('contents')->keyBy('user_id')->all();
    }
}