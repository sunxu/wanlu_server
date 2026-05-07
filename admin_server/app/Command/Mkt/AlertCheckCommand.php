<?php

declare(strict_types=1);

namespace App\Command\Mkt;

use App\Service\Mkt\AlertCheckService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class AlertCheckCommand extends HyperfCommand
{
    /**
     * @var AlertCheckService
     */
    protected $alertCheckService;

    public function __construct()
    {
        $this->alertCheckService= make(AlertCheckService::class);
        parent::__construct('mkt:alertCheck');
    }

    public function handle()
    {
        $type = $this->input->getArgument('type') ?: 'all';

        $typeName = $type === 'advertiser' ? '广告主' : ($type === 'project' ? '项目' : '全部');
        $this->info("开始检查报警规则({$typeName}) " . date('Y-m-d H:i:s'));

        try {
            if ($type === 'advertiser') {
                $this->alertCheckService->checkAdvertiserRules();
            } elseif ($type === 'project') {
                $this->alertCheckService->checkProjectRules();
            } else {
                $this->alertCheckService->checkAllRules();
            }
            $this->info('报警规则检查完成');
        } catch (\Throwable $e) {
            $this->error('检查失败: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function getArguments()
    {
        return [
            ['type', InputArgument::OPTIONAL, '检查类型: advertiser(广告主) 或 project(项目)']
        ];
    }
}
