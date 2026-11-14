<?php
declare(strict_types=1);

namespace App\Command\Mkt;

use App\Service\Mkt\TencentPrivateCredentialService;
use App\Service\Mkt\TencentPrivateImportError;
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
        } catch (TencentPrivateImportError $e) {
            // 仅向控制台返回我们自己定义的固定失败代码，不转发腾讯或 SQL 原始信息。
            $reasons = [
                TencentPrivateImportError::INVALID_INPUT => '命令参数缺失或格式不正确',
                TencentPrivateImportError::SECRET_STORAGE_LIMIT => 'client_secret 超出数据库 mkt_app.app_secret 的 100 字节容量',
                TencentPrivateImportError::APP_NOT_ACTIVE => '业务 App 不存在或未启用',
                TencentPrivateImportError::DEVELOPER_APP_CONFLICT => '开发者应用 ID、平台或密钥与已保存信息冲突',
                TencentPrivateImportError::CREDENTIAL_ALREADY_EXISTS => '业务 App 已有凭证；替换请加 --replace，跨 client_id 迁移不支持',
                TencentPrivateImportError::TOKEN_ALREADY_BOUND => '该 Token 已被另一个业务 App 使用',
                TencentPrivateImportError::TENCENT_NETWORK_FAILED => '腾讯刷新请求未得到可用响应，请检查网络、TLS、HTTP 状态',
                TencentPrivateImportError::TENCENT_REFRESH_REJECTED => '腾讯拒绝刷新或响应格式不符合协议，请核对密钥、令牌和生效状态',
                TencentPrivateImportError::TENCENT_TOKEN_RESPONSE_INVALID => '腾讯返回的 Token 或有效期缺失或不合法',
                TencentPrivateImportError::DB_READ_FAILED => '数据库预检读取失败，请检查 uc 连接、表结构与只读权限',
                TencentPrivateImportError::DB_WRITE_FAILED => '数据库写入失败，请检查表结构和数据库错误日志（注意脱敏）',
            ];
            $code = $e->getMessage();
            if (!isset($reasons[$code])) {
                $code = 'UNKNOWN_ERROR';
            }
            throw new RuntimeException('腾讯私有凭证导入失败 [' . $code . ']：' . ($reasons[$code] ?? '发生未分类异常'));
        } catch (\Throwable $e) {
            // 数据库预检、框架初始化等意外异常也不能泄露敏感参数。
            throw new RuntimeException('腾讯私有凭证导入失败 [INTERNAL_ERROR]：请检查应用数据库连接及脱敏后的服务端日志');
        }
        $this->line('腾讯私有凭证已登记，内部记录：' . $id);
    }
}
