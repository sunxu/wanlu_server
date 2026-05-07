<?php

declare(strict_types=1);

namespace App\Command\Mkt;

use App\Constants\MktConst;
use App\Job\Mkt\MktJob;
use App\Model\Mkt\MktAdvertiser;
use App\Service\Mkt\MktService;
use App\Utils\Log;
use Exception;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class ReportDailyItem extends HyperfCommand
{
    protected $name = 'mkt:reportDailyItem';

    /**
     * 字段
     * @var string
     */
    private $key = 'advertiser_id';

    private $appCode = 'app_code';

    /**
     * 方法名
     * @var string
     */
    private $func = [
        'advertiser' => 'reportDailyAdvertiser',
        'project' => 'reportDailyProject',
        'ad' => 'reportDailyAd',
        'plan' => 'reportDailyPlan',
    ];

    protected function getArguments()
    {
        return [
            ['item', InputArgument::REQUIRED, '层级'],
            ['date', InputArgument::OPTIONAL, '日期', date('Y-m-d')],
            [$this->appCode, InputArgument::OPTIONAL, '应用'],
            [$this->key, InputArgument::OPTIONAL, '广告主'],
        ];
    }

    public function handle()
    {
        $args = $this->input->getArguments();

        $item = data_get($args, 'item');
        $date = data_get($args, 'date');
        $appCode = data_get($args, $this->appCode);
        $value = data_get($args, $this->key);

        if (!isset($this->func[$item])) {
            throw new Exception('invalid item');
        }

        // 日期
        $dateArr = [
            'today' => date('Y-m-d'),
            'yesterday' => date('Y-m-d', strtotime('-1 days')),
            'beforeYesterday' => date('Y-m-d', strtotime('-2 days')),
        ];

        $date = $dateArr[$date] ?? $date;

        $model = $this->getMode();

        !empty($appCode) && $model->where($this->appCode, $appCode);
        !empty($value) && $model->where($this->key, $value);
        // 有消耗的广告主
        $item != 'advertiser' && $model->whereExists(function ($query) use ($date) {
            $query->select(Db::raw(1))
                ->from('report_daily_advertiser')
                ->whereRaw("report_daily_advertiser.advertiser_id = mkt_advertiser.advertiser_id AND report_daily_advertiser.date = '{$date}'");
        });

        $list = $model
            ->whereIn($this->appCode, array_keys($this->getAppList()))
            ->pluck($this->key)
            ->toArray();

        // 规避帐户聚集触发频控
        shuffle($list);

        Log::http('info', '【ReportDailyItem】', [
            'func' =>  $this->func[$item],
            'item' => $item,
            'data' => $list,
            'date' => $date,
        ]);

        foreach ($list as $v) {
            usleep(200*1000);
            queue_push(new MktJob($this->func[$item], [(string)$v, (string)$date]), MktConst::QUEUE_KEY);
        }
    }

    public function getAppList()
    {
        // 自投应用列表
        return make(MktService::class)->appListPutSelf();
    }

    public function getMode()
    {
        return MktAdvertiser::query()
            ->where('status', 1)
            ->where('account_id', '!=', '');
    }
}
