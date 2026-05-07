<?php

declare(strict_types=1);

namespace App\Command\Mkt;

use App\Constants\MktConst;
use App\Job\Mkt\MktJob;
use App\Model\Mkt\MktUser;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class AccountList extends HyperfCommand
{
    protected $name = 'mkt:accountList';

    /**
     * 字段
     * @var string
     */
    private $key = 'user_id';

    /**
     * 方法名
     * @var string
     */
    private $func = 'accountList';

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

        $list = $model->pluck($this->key)->toArray();

        foreach ($list as $v) {
            queue_push(new MktJob($this->func, [(string)$v]), MktConst::QUEUE_KEY);
        }
    }

    public function getMode()
    {
        return MktUser::query();
    }
}
