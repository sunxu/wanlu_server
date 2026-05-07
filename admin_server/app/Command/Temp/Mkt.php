<?php


namespace App\Command\Temp;

use App\Constants\AppConst;
use App\Model\App;
use App\Service\Cash\EcpmService;
use App\Service\Cash\RevenueService;
use App\Service\Placement\LaunchBaseService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\DbConnection\Db;


/**
 * @Command
 */
class Mkt extends HyperfCommand
{
    protected $name = 'temp:mkt';

    /**
     * Handle the current command.
     */
    public function handle()
    {
        return $this->media();

        $list = Db::table('report_daily_advertiser')
            ->select([
                'app_code',
                'cost',
                'date',
                'advertiser_id',
                'hour_info',
            ])
            ->whereBetween('date', ['2023-05-25', '2023-06-01'])
            ->whereIn('advertiser_id', [
                '1766756300720142',
                '1764298826721416',
                '1765470636683277',
            ])
            ->get()
            ->map(function ($item) {
                return object_to_arr($item);
            })
            ->toArray();
        $insert = [];
        foreach ($list as $item) {
            $hourInfo = json_decode($item['hour_info'], true);
            if (empty($hourInfo)) {
                continue;
            }
            foreach ($hourInfo as $hour => $info) {
                $insert[] = [
                    'advertiser_id' => $item['advertiser_id'],
                    'date' => $item['date'],
                    'app_code' => $item['app_code'],
                    'date_cost' => $item['cost'],
                    'hour' => $hour,
                    'cost' => $info['cost'],
                    'active' => $info['active'],
                    'key_action' => $info['key_action'],
                ];
            }
        }

        DB::table('temp_0602')->insert($insert);
    }

    /**
     * 回本率刷新
     * @param $date
     */
    protected function revenue($date = null)
    {
        $this->info('Begin:' . date("Y-m-d H:i:s", time()));
        $date = $date ?? date('Y-m-d', strtotime('-7 days'));
        for (; strtotime($date) <= strtotime(date('Y-m-d', strtotime('-1 day'))); $date = date('Y-m-d', strtotime($date) + 86400)) {
            echo $date . PHP_EOL;
            (new RevenueService())->scheduleRevenue($date);
        }
        $this->info('End:' . date("Y-m-d H:i:s", time()));
    }

    /**
     * ECPM刷新
     * @param $date
     */
    protected function ecpm($date = null)
    {
        $this->info('Begin:' . date("Y-m-d H:i:s", time()));
//        $date = $date ?? date('Y-m-d', strtotime('-7 days'));
//        for (; strtotime($date) <= strtotime(date('Y-m-d', strtotime('-1 day'))); $date = date('Y-m-d', strtotime($date) + 86400)) {
//            echo $date . PHP_EOL;
        (new EcpmService())->scheduleInstalled('mckdx', '2023-06-25');
//        }
        $this->info('End:' . date("Y-m-d H:i:s", time()));
    }

    /**
     * 代投整体日报
     */
    protected function launch()
    {
        for ($date = '2023-07-01'; strtotime($date) < strtotime('-1 day'); $date = date('Y-m-d', strtotime($date) + 86400)) {
            (new LaunchBaseService())->scheduleRevenue($date);
        }
    }

    protected function media()
    {
        for ($date = '2023-07-01'; strtotime($date) <= strtotime('-1 days'); $date = date('Y-m-d', strtotime($date) + 86400)) {
//            $this->call('mkt:lianyun', [
//                'date' => $date
//            ]);
//            sleep(5);
            $this->call('media:dailyApp', [
                'date' => $date
            ]);
            $this->call('media:dailyLianyun', [
                'date' => $date
            ]);
            $this->call('media:dailyCompany', [
                'date' => $date
            ]);
        }
    }
}