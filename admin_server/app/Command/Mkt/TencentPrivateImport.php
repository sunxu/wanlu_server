<?php
declare(strict_types=1);

namespace App\Command\Mkt;

use App\Service\Mkt\TencentPrivateCredentialService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * @Command
 *
 * 从命令行直接导入腾讯私有应用凭证，不读取 JSON 文件。
 * 注意：命令行密钥可出现在 ps、进程审计和 Shell 历史中；仅在可信机器上运行。
 */
class TencentPrivateImport extends HyperfCommand
{
    protected $name = 'mkt:txPrivateImport';

    protected function getArguments()
    {
        return [
            ['app_code', InputArgument::REQUIRED, '业务 App Code'],
            ['client_id', InputArgument::REQUIRED, '腾讯开发者应用 ID'],
            ['client_secret', InputArgument::REQUIRED, '开发者应用密钥（敏感）'],
            ['refresh_token', InputArgument::REQUIRED, '腾讯 Refresh Token（敏感）'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['replace', null, InputOption::VALUE_NONE, '明确允许替换同一个业务 App 的现有凭证'],
        ];
    }

    public function handle()
    {
        $appCode = (string)$this->input->getArgument('app_code');
        try {
            $id = make(TencentPrivateCredentialService::class)->import($appCode, [
                'client_id' => (string)$this->input->getArgument('client_id'),
                'client_secret' => (string)$this->input->getArgument('client_secret'),
                'refresh_token' => (string)$this->input->getArgument('refresh_token'),
                'replace_existing' => (bool)$this->input->getOption('replace'),
            ]);
        } catch (\Throwable $e) {
            // 不将腾讯异常、参数值或网络请求地址带进命令行错误堆栈。
            throw new RuntimeException('腾讯私有凭证导入失败，请核实 App、开发者应用、Token 是否已生效及是否存在授权冲突');
        }
        $this->line('腾讯私有凭证已登记，内部记录：' . $id);
    }
}
