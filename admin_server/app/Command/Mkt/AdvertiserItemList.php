<?php

declare(strict_types=1);

namespace App\Command\Mkt;

use App\Constants\MktConst;
use App\Job\Mkt\MktJob;
use App\Model\Mkt\MktAdvertiser;
use App\Service\Mkt\MktService;
use Exception;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class AdvertiserItemList extends HyperfCommand
{
    protected $name = 'mkt:advertiserItemList';

    /**
     * 字段
     * @var string
     */
    protected $key = 'advertiser_id';

    protected $appCode = 'app_code';

    /**
     * 方法名
     * @var string
     */
    protected $func = [
        'project' => 'projectList',
        'ad' => 'adList',
        'plan' => 'planList',
    ];

    protected function getArguments()
    {
        return [
            ['item', InputArgument::REQUIRED, '层级'],
            [$this->appCode, InputArgument::OPTIONAL, '应用'],
            [$this->key, InputArgument::OPTIONAL, '广告主'],
        ];
    }

    public function handle()
    {
        $args = $this->input->getArguments();

        $item = data_get($args, 'item');
        $appCode = data_get($args, $this->appCode);
        $value = data_get($args, $this->key);

        if (!isset($this->func[$item])) {
            throw new Exception('invalid item');
        }

        $model = $this->getMode();

        !empty($appCode) && $model->where($this->appCode, $value);
        !empty($value) && $model->where($this->key, $value);

        // 仅处理自投应用
        $list = $model
            ->whereIn($this->appCode, array_keys($this->getAppList()))
            ->pluck($this->key)
            ->toArray();

        // 规避帐户聚集触发频控
        shuffle($list);

        foreach ($list as $v) {
            queue_push(new MktJob($this->func[$item], [(string)$v]), MktConst::QUEUE_KEY);
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
