<?php

declare(strict_types=1);

namespace App\Command\Mkt;

use App\Constants\MktConst;
use App\Job\Mkt\MktJob;
use App\Model\Mkt\MktAccount;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class AdvertiserBudget extends HyperfCommand
{
    protected $name = 'mkt:advertiserBudget';

    /**
     * 字段
     * @var string
     */
    private $key = 'account_id';

    /**
     * 方法名
     * @var string
     */
    private $func = 'advertiserBudget';

    protected function getArguments()
    {
        return [
            [$this->key, InputArgument::OPTIONAL],
        ];
    }

    public function handle()
    {
        $value = $this->input->getArgument($this->key);

        $model = $this->getMode();

        !empty($value) && $model->where($this->key, $value);

        // 仅处理有效授权
        $list = $model->where('is_valid', 1)->pluck($this->key)->toArray();

        foreach ($list as $v) {
            $advertiserIds = Db::table('report_daily_advertiser as rda')
                ->leftJoin('mkt_advertiser as ma', 'ma.advertiser_id', '=', 'rda.advertiser_id')
                ->where('rda.date', date('Y-m-d'))
                ->where('ma.account_id', $v)
                ->whereIn('rda.platform', [MktConst::PLATFORM_JL, MktConst::PLATFORM_KS, MktConst::PLATFORM_TX])
                ->pluck('rda.advertiser_id')
                ->toArray();

            if ($advertiserIds) {
                $arr = array_chunk($advertiserIds, 100);
                foreach ($arr as $ids) {
                    queue_push(new MktJob($this->func, [$v, $ids]), MktConst::QUEUE_KEY);
                }
            }
        }
    }

    public function getMode()
    {
        return MktAccount::query();
    }
}
