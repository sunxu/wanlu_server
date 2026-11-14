<?php
declare(strict_types=1);

namespace App\Command\Mkt;

use App\Constants\MktConst;
use App\Model\App;
use App\Model\Mkt\MktAccount;
use App\Model\Mkt\MktAdvertiser;
use App\Model\Mkt\MktApp;
use App\Model\Mkt\MktUser;
use App\Service\Mkt\TencentPrivateRefreshWindow;
use App\Service\Mkt\TxPrivateCredentialId;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 *
 * Read-only private-token readiness inspection:
 * never outputs secrets, sends network calls, changes account ownership or enables Callback.
 */
class TencentPrivatePreflight extends HyperfCommand
{
    protected $name = 'mkt:txPrivatePreflight';

    protected function getArguments()
    {
        return [
            ['app_code', InputArgument::REQUIRED, '业务 App Code'],
        ];
    }

    public function handle()
    {
        $appCode = (string)$this->input->getArgument('app_code');
        try {
            $userId = TxPrivateCredentialId::fromAppCode($appCode);
        } catch (\InvalidArgumentException $e) {
            throw new RuntimeException('TX_PRIVATE_PREFLIGHT_INVALID_APP_CODE');
        }

        try {
            $now = time();
            $appEnabled = App::query()
                ->where('app_code', $appCode)->where('status', 1)->exists();
            $token = MktUser::query()->where('user_id', $userId)
                ->first(['user_id', 'app_id', 'access_token', 'access_time', 'refresh_token', 'refresh_time']);
            $developer = $token ? MktApp::query()
                ->where('app_id', (string)$token->app_id)->first(['app_id', 'platform']) : null;

            $adList = MktAdvertiser::query()
                ->where('app_code', $appCode)
                ->where('platform', MktConst::PLATFORM_TX)
                ->where('status', 1)
                ->get(['advertiser_id', 'account_id']);
            $adCount = count($adList);
            $validAccountCount = 0;
            $missingAccounts = 0;
            foreach ($adList as $ad) {
                $account = MktAccount::query()->where('account_id', (string)$ad->account_id)
                    ->where('is_valid', 1)->first(['account_id']);
                if ($account) {
                    $validAccountCount++;
                } else {
                    $missingAccounts++;
                }
            }
        } catch (\Throwable $e) {
            // Database exceptions might contain secrets: never include original error or previous.
            throw new RuntimeException('TX_PRIVATE_PREFLIGHT_DB_READ_FAILED');
        }

        $accessExpiry = $token ? (int)$token->access_time : 0;
        $refreshExpiry = $token ? (int)$token->refresh_time : 0;
        $checks = [
            'app_active' => $appEnabled,
            'private_credential_exists' => $token !== null,
            'developer_platform_tx' => $developer !== null
                && (string)$developer->platform === MktConst::PLATFORM_TX,
            'access_token_valid' => $token !== null
                && (string)$token->access_token !== ''
                && $accessExpiry > $now + 60,
            'refresh_token_usable_locally' => $token !== null
                && (string)$token->refresh_token !== ''
                && ($refreshExpiry === 0 || $refreshExpiry > $now),
            'has_enabled_tx_advertiser' => $adCount > 0,
            'every_advertiser_account_valid' => $adCount > 0 && $missingAccounts === 0,
        ];

        // Config indicates the scheduler is enabled, not proof the process/queue consumer is alive.
        $crontabEnabled = filter_var(config('crontab.enable', false), FILTER_VALIDATE_BOOLEAN);
        $checks['crontab_config_enabled'] = $crontabEnabled;

        $warnings = [];
        if ($accessExpiry > 0 && $refreshExpiry > 0 && $refreshExpiry <= $accessExpiry) {
            $warnings[] = 'REFRESH_EXPIRES_NOT_LATER_THAN_ACCESS';
        }
        if ($token && $accessExpiry > 0
            && $accessExpiry < $now + TencentPrivateRefreshWindow::PRIVATE_LEAD_SECONDS) {
            $warnings[] = 'TOKEN_IN_24H_REFRESH_WINDOW';
        }
        if (!$crontabEnabled) {
            $warnings[] = 'CRONTAB_NOT_ENABLED_IN_THIS_PROCESS';
        }
        $output = [
            'schema' => 'tx_private_local_preflight_v1',
            'app_code' => $appCode,
            'private_credential_id' => $userId,
            'checks' => $checks,
            'advertisers' => [
                'enabled_count' => $adCount,
                'valid_account_count' => $validAccountCount,
                'invalid_or_missing_account_count' => $missingAccounts,
            ],
            'expiry' => [
                'access_expires_at' => $accessExpiry > 0 ? date('c', $accessExpiry) : null,
                'refresh_expires_at' => $refreshExpiry > 0 ? date('c', $refreshExpiry) : null,
                'refresh_expiry_unknown' => $refreshExpiry === 0 && $token !== null,
            ],
            'warnings' => $warnings,
            'unverified' => [
                'crontab_process_running',
                'mkt_queue_consumer_running',
                'tencent_advertiser_permissions',
                'api_server_callback_enabled',
                'datanexus_callback_url_and_click_storage',
                'real_conversion_delivery',
            ],
            'database_modified' => false,
            'network_requests' => 0,
            'callback_enabled_by_command' => false,
            'status' => !in_array(false, $checks, true)
                ? 'LOCAL_PREFLIGHT_PASS__EXTERNAL_INTEGRATION_HOLD'
                : 'LOCAL_PREFLIGHT_HOLD',
        ];
        $this->line((string)json_encode(
            $output,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        ));
    }
}
