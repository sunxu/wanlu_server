<?php
declare(strict_types=1);

namespace App\Command\Mkt;

use App\Constants\MktConst;
use App\Library\Mkt\MktTx;
use App\Library\Mkt\TencentAdvertiserProbeError;
use App\Model\App;
use App\Model\Mkt\MktAccount;
use App\Model\Mkt\MktAdvertiser;
use App\Model\Mkt\MktApp;
use App\Model\Mkt\MktUser;
use App\Service\Mkt\TxPrivateCredentialId;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 *
 * 腾讯私有 Token 指定广告主只读权限探测。
 * 仅 GET /advertiser/get：不新增账户、不改绑定、不发转化、不刷新 Token。
 */
class TencentPrivateAccountProbe extends HyperfCommand
{
    protected $name = 'mkt:txPrivateAccountProbe';

    protected function getArguments()
    {
        return [
            ['app_code', InputArgument::REQUIRED, '业务 App Code'],
            ['account_id', InputArgument::REQUIRED, '真实腾讯广告主 account_id（不是开发者 client_id）'],
        ];
    }

    public function handle()
    {
        $appCode = (string)$this->input->getArgument('app_code');
        $accountId = (string)$this->input->getArgument('account_id');
        if (!preg_match('/^[1-9][0-9]{0,19}$/D', $accountId)) {
            throw new RuntimeException('TX_ACCOUNT_PROBE_INVALID_ACCOUNT_ID');
        }
        try {
            $credentialId = TxPrivateCredentialId::fromAppCode($appCode);
        } catch (\InvalidArgumentException $e) {
            throw new RuntimeException('TX_ACCOUNT_PROBE_INVALID_APP_CODE');
        }

        try {
            $appEnabled = App::query()->where('app_code', $appCode)
                ->where('status', 1)->exists();
            $credential = MktUser::find($credentialId);
            $developer = $credential ? MktApp::find((string)$credential->app_id) : null;
            $existingAccount = MktAccount::find($accountId);
            $existingAdvertiser = MktAdvertiser::find($accountId);
        } catch (\Throwable $e) {
            // 禁止在终端输出 SQL 异常及可能附带的真实凭证。
            throw new RuntimeException('TX_ACCOUNT_PROBE_DB_READ_FAILED');
        }

        if (!$appEnabled || !$credential || !$developer
            || (string)$developer->platform !== MktConst::PLATFORM_TX
            || (string)$credential->app_id !== (string)$developer->app_id) {
            throw new RuntimeException('TX_ACCOUNT_PROBE_LOCAL_CREDENTIAL_NOT_READY');
        }
        if ((string)$credential->access_token === ''
            || (int)$credential->access_time <= time() + 60) {
            throw new RuntimeException('TX_ACCOUNT_PROBE_ACCESS_TOKEN_EXPIRED');
        }

        // 只将必要的敏感值传入官方读取 API，不输出完整响应、HTTP URL 或任何 Token。
        try {
            $list = $this->fetchTencentAdvertiser(
                (string)$developer->app_id,
                (string)$developer->app_secret,
                (string)$credential->access_token,
                $accountId
            );
        } catch (TencentAdvertiserProbeError $e) {
            // Only fixed reasons and strictly bounded numeric codes are printed.
            // Do not propagate Tencent's original message or URL into exception/trace.
            $details = 'TX_ACCOUNT_PROBE_' . $e->getMessage();
            if ($e->httpStatus() !== null) {
                $details .= ' HTTP_STATUS=' . $e->httpStatus();
            }
            if ($e->apiCode() !== null) {
                $details .= ' API_CODE=' . $e->apiCode();
            }
            throw new RuntimeException($details);
        } catch (\Throwable $e) {
            throw new RuntimeException('TX_ACCOUNT_PROBE_REMOTE_ADVERTISER_GET_FAILED');
        }
        if (count($list) !== 1 || (string)($list[0]['account_id'] ?? '') !== $accountId) {
            throw new RuntimeException('TX_ACCOUNT_PROBE_REMOTE_IDENTITY_MISMATCH');
        }

        $otherBinding = $existingAdvertiser !== null
            && ((string)$existingAdvertiser->platform !== MktConst::PLATFORM_TX
                || (string)$existingAdvertiser->app_code !== $appCode
                || (string)$existingAdvertiser->account_id !== $accountId);
        $boundHere = $existingAdvertiser !== null && !$otherBinding;
        $output = [
            'schema' => 'tx_private_account_probe_v1',
            'app_code' => $appCode,
            'verified_account_id' => $accountId,
            'remote_advertiser_accessible' => true,
            'existing_mkt_account' => $existingAccount !== null,
            'existing_mkt_account_is_valid' => $existingAccount !== null
                && (int)$existingAccount->is_valid === 1,
            'existing_advertiser_bound_to_this_app' => $boundHere,
            'global_advertiser_id_conflict' => $otherBinding,
            'account_user_id_not_modified' => true,
            'database_modified' => false,
            'token_refreshed' => false,
            'callback_enabled' => false,
            'remote_request' => 'GET advertiser/get',
            'status' => $otherBinding
                ? 'REMOTE_ACCESS_PASS__EXISTING_OWNERSHIP_HOLD'
                : 'REMOTE_ACCESS_PASS__MANUAL_BINDING_REQUIRED',
        ];
        $this->line((string)json_encode(
            $output,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        ));
    }

    /** 覆盖用于离线 Mock；正式环境只执行 MktTx 的 advertiser/get 查询。 */
    protected function fetchTencentAdvertiser(
        string $clientId,
        string $clientSecret,
        string $accessToken,
        string $accountId
    ): array {
        return (new MktTx())
            ->setApp($clientId, $clientSecret)
            ->setToken($accessToken)
            ->advertiserProbe($accountId);
    }
}
