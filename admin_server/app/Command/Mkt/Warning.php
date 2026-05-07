<?php
// 投放成本监控
declare(strict_types=1);

namespace App\Command\Mkt;

use App\Model\App;
use App\Utils\Ding;
use App\Utils\RedisManager;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class Warning extends HyperfCommand
{
    protected $name = 'mkt:warning';

    /**
     * 最大激活成本
     * @var float
     */
    private $maxActiveCost = 8.0;

    /**
     * 最小激活成本
     * @var float
     */
    private $minActiveCost = 1.0;

    /**
     * 有效最低消耗
     * @var int
     */
    private $minCost = 50;

    /**
     * 重复帐户每N小时播报一次
     * @var int
     */
    private $intervalHour = 1;

    /**
     * 最大倍数
     * @var float
     */
    private $maxMultiple = 3;

    public function handle()
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');

        $list = $this->getList($yesterday);
        $app = $this->getApp();

        $redis = RedisManager::get();
        $warning = [];

        foreach ($list as $advertiserId => $item) {
            if (!isset($item[$today])) {
                continue;
            }

            if (
                (isset($item[$yesterday]) && ($item[$today]['active_cost'] / $item[$yesterday]['active_cost'] > $this->maxMultiple))
                || $item[$today]['active_cost'] > $this->maxActiveCost
            ) {
                $redisKey = 'warning-advertiser:' . $advertiserId;
                if ($redis->setnx($redisKey, 1) && $redis->expire($redisKey, $this->intervalHour * 3600)) {
                    $warning[$app[$item[$today]['app_code']] ?? $item[$today]['app_code']][] = [
                        '广告主' => $advertiserId . '|' . $item[$today]['advertiser_name'],
                        '激活成本' => $item[$today]['active_cost']
                    ];
                };
            }
        }

        $this->push($warning);
    }

    private function getList(string $date)
    {
        $list = Db::table('report_daily_advertiser as rda')
            ->leftJoin('mkt_advertiser as ma', 'ma.advertiser_id', '=', 'rda.advertiser_id')
            ->select([
                'ma.advertiser_name',
                'rda.advertiser_id',
                'rda.date',
                'rda.app_code',
                'rda.cost',
                'rda.active',
                'rda.active_cost'
            ])
            ->where('rda.active_cost', '>', $this->minActiveCost)
            ->where('rda.cost', '>', $this->minCost)
            ->where('rda.date', '>=', $date)
            ->where('ma.status', '=', 1)
            ->get()
            ->map(function ($item) {
                return object_to_arr($item);
            })->toArray();

        $data = [];
        foreach ($list as $v) {
            $data[$v['advertiser_id']][$v['date']] = $v;
        }
        return $data;
    }

    private function getApp()
    {
        return App::query()->pluck('app_name', 'app_code')->toArray();
    }

    private function push(array $list)
    {
        if (empty($list)) {
            return;
        }

        $message = '**投放成本告警**';
        $message .= PHP_EOL . PHP_EOL;
        foreach ($list as $app => $item) {
            $message .= '---';
            $message .= PHP_EOL . PHP_EOL;
            $message .= "**应用：{$app}** ";
            foreach ($item as $d) {
                foreach ($d as $key => $value) {
                    $message .= PHP_EOL . PHP_EOL;
                    $message .= "**{$key}：** " . $value;
                }
            }
            $message .= PHP_EOL . PHP_EOL;
        }

        make(Ding::class)->setRobotCode('mkt_put')->pushMarkdown('投放警报', $message);
    }
}
