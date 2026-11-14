<?php
declare(strict_types=1);

namespace App\Command\Mkt;

use App\Constants\MktConst;
use App\Library\Mkt\MktTx;
use App\Model\App;
use App\Model\Mkt\MktAccount;
use App\Model\Mkt\MktAdvertiser;
use App\Model\Mkt\MktApp;
use App\Model\Mkt\MktUser;
use App\Service\Mkt\TencentPrivateBindingGuard;
use App\Service\Mkt\TxPrivateCredentialId;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\DbConnection\Db;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * @Command
 *
 * Verified private Tencent advertiser binding:
 * dry-run by default; --apply creates only absent records in one DB transaction.
 * Never overwrites an account owner or advertiser from any platform.
 */
class TencentPrivateBind extends HyperfCommand
{
    protected $name = 'mkt:txPrivateBind';

    protected function getArguments()
    {
        return [
            ['app_code', InputArgument::REQUIRED, '已导入腾讯私有凭证的业务 App'],
            ['account_id', InputArgument::REQUIRED, '腾讯广告主 account_id'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['apply', null, InputOption::VALUE_NONE, '明确确认创建缺失的绑定；默认只读检查'],
        ];
    }

    public function handle()
    {
        $appCode = (string)$this->input->getArgument('app_code');
        $accountId = (string)$this->input->getArgument('account_id');
        $apply = (bool)$this->input->getOption('apply');

        if (!preg_match('/^[1-9][0-9]{0,19}$/D', $accountId)) {
            throw new RuntimeException('TX_BIND_INVALID_ACCOUNT_ID');
        }
        try {
            $privateUserId = TxPrivateCredentialId::fromAppCode($appCode);
        } catch (\InvalidArgumentException $e) {
            throw new RuntimeException('TX_BIND_INVALID_APP_CODE');
        }

        try {
            $appActive = App::query()
                ->where('app_code', $appCode)->where('status', 1)->exists();
            $credential = MktUser::find($privateUserId);
            $developer = $credential ? MktApp::find((string)$credential->app_id) : null;
        } catch (\Throwable $e) {
            throw new RuntimeException('TX_BIND_DB_READ_FAILED');
        }
        if (!$appActive || !$credential || !$developer
            || (string)$developer->platform !== MktConst::PLATFORM_TX
            || (string)$credential->app_id !== (string)$developer->app_id
            || (string)$credential->access_token === ''
            || (int)$credential->access_time <= time() + 60) {
            throw new RuntimeException('TX_BIND_CREDENTIAL_NOT_READY');
        }

        // Revalidate the actual account using the private credential.
        // The verified corporation_name is taken from Tencent, never inferred
        // from a developer application, a JL advertiser or an unrelated account.
        try {
            $remote = $this->fetchRemoteAdvertiser(
                (string)$developer->app_id,
                (string)$developer->app_secret,
                (string)$credential->access_token,
                $accountId
            );
        } catch (\Throwable $e) {
            throw new RuntimeException('TX_BIND_TENCENT_READ_FAILED');
        }
        if (count($remote) !== 1
            || (string)($remote[0]['account_id'] ?? '') !== $accountId
            || (string)($remote[0]['advertiser_id'] ?? '') !== $accountId) {
            throw new RuntimeException('TX_BIND_REMOTE_ACCOUNT_MISMATCH');
        }
        $name = trim((string)($remote[0]['advertiser_name'] ?? ''));
        if ($name === '' || strlen($name) > 100) {
            throw new RuntimeException('TX_BIND_REMOTE_ADVERTISER_NAME_INVALID');
        }
        $accessTokenUsed = (string)$credential->access_token;

        try {
            // The transaction is intentionally used for the dry-run as well,
            // giving both modes identical ownership/race checks. Only --apply writes.
            $outcome = Db::transaction(function () use (
                $appCode, $accountId, $privateUserId, $name, $apply, $accessTokenUsed
            ) {
                $latestCredential = MktUser::query()
                    ->where('user_id', $privateUserId)->lockForUpdate()->first();
                if (!$latestCredential
                    || !hash_equals((string)$latestCredential->access_token, $accessTokenUsed)
                    || (int)$latestCredential->access_time <= time() + 60) {
                    throw new RuntimeException('TX_BIND_CREDENTIAL_CHANGED_HOLD');
                }

                $account = MktAccount::query()
                    ->where('account_id', $accountId)->lockForUpdate()->first();
                $advertiser = MktAdvertiser::query()
                    ->where('advertiser_id', $accountId)->lockForUpdate()->first();

                $guard = TencentPrivateBindingGuard::inspect(
                    $account ? [
                        'user_id' => $account->user_id,
                        'is_valid' => $account->is_valid,
                    ] : null,
                    $advertiser ? [
                        'advertiser_id' => $advertiser->advertiser_id,
                        'account_id' => $advertiser->account_id,
                        'platform' => $advertiser->platform,
                        'app_code' => $advertiser->app_code,
                        'status' => $advertiser->status,
                    ] : null,
                    $privateUserId,
                    $appCode,
                    $accountId
                );

                if (!in_array($guard['status'], ['TX_BIND_READY', 'TX_BIND_ALREADY_PRESENT'], true)) {
                    return ['status' => $guard['status'], 'created_account' => false, 'created_advertiser' => false];
                }
                if (!$apply) {
                    return [
                        'status' => $guard['status'] === 'TX_BIND_ALREADY_PRESENT'
                            ? 'TX_BIND_ALREADY_PRESENT' : 'TX_BIND_DRY_RUN_READY',
                        'would_create_account' => $guard['create_account'],
                        'would_create_advertiser' => $guard['create_advertiser'],
                        'created_account' => false,
                        'created_advertiser' => false,
                    ];
                }

                if ($guard['create_account']) {
                    $newAccount = new MktAccount();
                    $newAccount->fill([
                        'account_id' => $accountId,
                        'account_name' => $name,
                        'account_role' => 'PRIVATE_APP',
                        'is_valid' => 1,
                        // This is a real imported private credential, not a
                        // fabricated OAuth identity. Never rewrite any owner.
                        'user_id' => $privateUserId,
                    ]);
                    if (!$newAccount->save()) {
                        throw new RuntimeException('TX_BIND_ACCOUNT_SAVE_FAILED');
                    }
                }
                if ($guard['create_advertiser']) {
                    $newAdvertiser = new MktAdvertiser();
                    $newAdvertiser->fill([
                        'advertiser_id' => $accountId,
                        'advertiser_name' => $name,
                        'account_id' => $accountId,
                        'platform' => MktConst::PLATFORM_TX,
                        'app_code' => $appCode,
                        'status' => 1,
                        'launch_type' => 0,
                        'active_type' => 2,
                        'action_type' => 0,
                        'keep_type' => 1,
                        'agency_name' => '',
                    ]);
                    if (!$newAdvertiser->save()) {
                        throw new RuntimeException('TX_BIND_ADVERTISER_SAVE_FAILED');
                    }
                }
                return [
                    'status' => 'TX_BIND_APPLIED',
                    'created_account' => $guard['create_account'],
                    'created_advertiser' => $guard['create_advertiser'],
                ];
            });
        } catch (RuntimeException $e) {
            // Whitelisted internal reasons contain no database values/secrets.
            if (in_array($e->getMessage(), [
                'TX_BIND_CREDENTIAL_CHANGED_HOLD',
                'TX_BIND_ACCOUNT_SAVE_FAILED',
                'TX_BIND_ADVERTISER_SAVE_FAILED',
            ], true)) {
                throw $e;
            }
            throw new RuntimeException('TX_BIND_DB_TRANSACTION_FAILED');
        } catch (\Throwable $e) {
            throw new RuntimeException('TX_BIND_DB_TRANSACTION_FAILED');
        }

        $this->line((string)json_encode(
            array_merge([
                'schema' => 'tx_private_advertiser_binding_v1',
                'app_code' => $appCode,
                'verified_account_id' => $accountId,
                'remote_access_verified' => true,
                'mode' => $apply ? 'apply' : 'dry_run',
                'callback_enabled_by_command' => false,
                'existing_oauth_owner_modified' => false,
            ], $outcome),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        ));
    }

    protected function fetchRemoteAdvertiser(
        string $clientId,
        string $clientSecret,
        string $accessToken,
        string $accountId
    ): array {
        return (new MktTx())->setApp($clientId, $clientSecret)
            ->setToken($accessToken)->advertiserGet($accountId);
    }
}
