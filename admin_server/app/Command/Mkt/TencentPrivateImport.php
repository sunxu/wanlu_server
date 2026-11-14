<?php
declare(strict_types=1);

namespace App\Command\Mkt;

use App\Service\Mkt\TencentPrivateCredentialService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 *
 * 仅从本机权限 0600 的 JSON 文件读取敏感参数，避免命令参数/历史/日志暴露。
 */
class TencentPrivateImport extends HyperfCommand
{
    protected $name = 'mkt:txPrivateImport';

    protected function getArguments()
    {
        return [
            ['app_code', InputArgument::REQUIRED, '业务 App Code'],
            ['secret_file', InputArgument::REQUIRED, '服务器本地私有 JSON 文件（0600）'],
        ];
    }

    public function handle()
    {
        $appCode = (string)$this->input->getArgument('app_code');
        $path = (string)$this->input->getArgument('secret_file');
        if (!is_file($path) || is_link($path) || !is_readable($path)
            || (fileperms($path) & 0077) !== 0 || filesize($path) > 8192) {
            throw new RuntimeException('凭证文件必须是可读取、非符号链接且权限为 0600 的小型本地文件');
        }
        $input = json_decode((string)file_get_contents($path), true);
        if (!is_array($input)) {
            throw new RuntimeException('凭证 JSON 格式错误');
        }
        try {
            $id = make(TencentPrivateCredentialService::class)->import($appCode, $input);
        } catch (\Throwable $e) {
            // 不输出第三方 API 信息或凭证输入，避免 CLI 堆栈暴露敏感信息。
            throw new RuntimeException('腾讯凭证导入失败；请检查业务 App、开发者应用、Token 有效期和身份冲突');
        }
        $this->line('腾讯私有凭证已登记，内部记录：' . $id);
    }
}
