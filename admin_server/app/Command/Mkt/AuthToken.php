<?php

declare(strict_types=1);

namespace App\Command\Mkt;

use App\Constants\MktConst;
use App\Job\Mkt\MktJob;
use App\Model\Mkt\MktUser;
use App\Service\Mkt\TencentPrivateRefreshWindow;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class AuthToken extends HyperfCommand
{
    protected $name = 'mkt:authToken';

    /**
     * 字段
     * @var string
     */
    private $key = 'user_id';

    /**
     * 方法名
     * @var string
     */
    private $func = 'authToken';

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

        $now = time();
        // 历史平台保持原先的提前20分钟逻辑，不改变授权轮换频率。
        $legacyDue = $model
            ->where('access_time', '<', $now + TencentPrivateRefreshWindow::LEGACY_LEAD_SECONDS)
            ->pluck($this->key)->toArray();

        // 腾讯私有 Token 的 Access/Refresh 可能同一天过期。
        // 在到期前24小时触发刷新，留出队列和 Tencent API 故障恢复余量。
        $accessModel = $this->getMode();
        $refreshModel = $this->getMode();
        if (!empty($value)) {
            $accessModel->where($this->key, $value);
            $refreshModel->where($this->key, $value);
        }
        // LIKE is only a broad pre-filter. mergeCandidates verifies the full ID.
        $privateByAccess = $accessModel
            ->where('user_id', 'like', 'txapp_%')
            ->where('access_time', '<', $now + TencentPrivateRefreshWindow::PRIVATE_LEAD_SECONDS)
            ->pluck($this->key)->toArray();
        $privateByRefresh = $refreshModel
            ->where('user_id', 'like', 'txapp_%')
            ->where('refresh_time', '>', 0)
            ->where('refresh_time', '<', $now + TencentPrivateRefreshWindow::PRIVATE_LEAD_SECONDS)
            ->pluck($this->key)->toArray();

        $list = TencentPrivateRefreshWindow::mergeCandidates(
            $legacyDue,
            array_merge($privateByAccess, $privateByRefresh)
        );
        foreach ($list as $v) {
            queue_push(new MktJob($this->func, [(string)$v]), MktConst::QUEUE_KEY);
        }
    }

    public function getMode()
    {
        return MktUser::query();
    }
}
