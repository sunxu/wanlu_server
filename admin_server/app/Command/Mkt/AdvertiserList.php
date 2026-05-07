<?php

declare(strict_types=1);

namespace App\Command\Mkt;

use App\Constants\MktConst;
use App\Job\Mkt\MktJob;
use App\Model\Mkt\MktAccount;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class AdvertiserList extends HyperfCommand
{
    protected $name = 'mkt:advertiserList';

    /**
     * 字段
     * @var string
     */
    private $key = 'account_id';

    /**
     * 方法名
     * @var string
     */
    private $func = 'advertiserList';

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
            queue_push(new MktJob($this->func, [(string)$v]), MktConst::QUEUE_KEY);
        }
    }

    public function getMode()
    {
        return MktAccount::query();
    }
}
