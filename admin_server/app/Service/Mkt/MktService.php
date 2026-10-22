<?php

declare(strict_types=1);

namespace App\Service\Mkt;

use App\Constants\AppConst;
use App\Constants\MktConst;
use App\Library\Mkt\MktAbstract;
use App\Model\App;
use App\Model\Mkt\MktProject;
use App\Model\Mkt\MktAccount;
use App\Model\Mkt\MktAd;
use App\Model\Mkt\MktAdvertiser;
use App\Model\Mkt\MktApp;
use App\Model\Mkt\MktPlan;
use App\Model\Mkt\MktUser;
use App\Model\Mkt\ReportDailyAd;
use App\Model\Mkt\ReportDailyAdvertiser;
use App\Model\Mkt\ReportDailyPlan;
use App\Model\Mkt\ReportDailyProject;
use App\Utils\Log;
use Exception;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Model;
use Hyperf\DbConnection\Db;

class MktService
{
    protected $app;

    protected $user;

    protected $token;

    /**
     * 授权用户
     * @param string $appId
     * @param string $authCode
     * @param string $userId
     * @throws Exception
     */
    public function authUser(string $appId, string $authCode, string $userId = null)
    {
        $app = $this->getApp($appId);

        if (!$app) {
            throw new Exception('invalid appId:' . $appId);
        }

        $mkt = $this->getMkt($app->platform)
            ->setApp((string)$app->app_id, (string)$app->app_secret)
            ->setAppInfo($app->toArray());


        // 百度
        $this->bd($app->platform, $authCode, $userId);

        $userToken = $mkt->authAccessToken($authCode);

        // 腾讯直客：先查询并校验身份，再原子保存现有三层授权关系。
        if ($app->platform === MktConst::PLATFORM_TX) {
            $accountId = $userToken['account_id'];
            $mkt->setToken($userToken['access_token']);
            $advertiser = $mkt->advertiserGet($accountId)[0];
            Db::transaction(function () use ($userToken, $accountId, $advertiser) {
                $user = MktUser::query()->where('user_id', $userToken['user_id'])->lockForUpdate()->first();
                if ($user && (string)$user->app_id !== (string)$userToken['app_id']) {
                    throw new Exception('腾讯授权用户ID与已有应用冲突');
                }
                $account = MktAccount::query()->where('account_id', $accountId)->lockForUpdate()->first();
                if ($account && (string)$account->user_id !== $userToken['user_id']) {
                    throw new Exception('腾讯账户ID与已有授权关系冲突');
                }
                $this->saveUser($userToken);
                $this->saveAccount([
                    'user_id' => $userToken['user_id'], 'account_id' => $accountId,
                    'account_name' => $advertiser['advertiser_name'],
                    'account_role' => $userToken['account_role'], 'is_valid' => 1,
                ]);
                $advertiser['platform'] = MktConst::PLATFORM_TX;
                $this->saveAdvertiser($advertiser);
            });
            return;
        }

        // 巨量
        if ($app->platform == MktConst::PLATFORM_JL) {

            $mkt->setToken($userToken['access_token']);

            $userInfo = $mkt->authUserInfo();

            $this->saveUser(array_merge($userToken, $userInfo ?? []));

            $data = $mkt->authAccountGet();
            foreach ($data as $value) {
                // 规避巨量纵横组织交叉授权破坏组织归宿的唯一性
                if ($value['account_role'] != 'CUSTOMER_ADMIN') {
                    continue;
                }
                $value['user_id'] = $userInfo['user_id'];
                $this->saveAccount($value);
            }
        }

        // 快手、华为
        if (in_array($app->platform, [MktConst::PLATFORM_KS, MktConst::PLATFORM_HW, MktConst::PLATFORM_BD])) {

            $userToken['user_id'] = empty($userId) ? $userToken['app_id'] : $userId;

            $this->saveUser($userToken);
            $this->saveAccount([
                'user_id' => $userToken['user_id'],
                'account_id' => $userToken['user_id'],
                'is_valid' => 1
            ]);
        }

        // vivo
        if (in_array($app->platform, [MktConst::PLATFORM_VI])) {
            // 二代指定用户
            if (!empty($userId)) {
                $userToken['user_id'] = $userId;

                $this->saveUser($userToken);
                $this->saveAccount([
                    'user_id' => $userToken['user_id'],
                    'account_id' => $userToken['user_id'],
                    'is_valid' => 1
                ]);
            } else {
                $mkt->setToken($userToken['access_token']);

                $userInfo = $mkt->authUserInfo();

                $this->saveUser(array_merge($userToken, $userInfo ?? []));

                $this->saveAccount([
                    'user_id' => $userInfo['user_id'],
                    'account_id' => $userInfo['user_id'],
                    'account_name' => $userInfo['user_name'],
                    'is_valid' => 1
                ]);
                // 判断帐户类型|账户等级: 默认-0,核代-1,二代-2,广告主-3,广告主（已解绑）-4
                if (($userInfo['user_level'] ?? -1) == 3) {
                    $this->saveAdvertiser([
                        'account_id' => $userInfo['user_id'],
                        'platform' => $app->platform,
                        'advertiser_id' => $userInfo['user_id'],
                        'advertiser_name' => $userInfo['user_name'],
                    ]);
                }
            }
        }
    }

    /**
     * 授权Token
     * @param string $userId
     * @return bool
     * @throws Exception
     */
    public function authToken(string $userId)
    {
        $model = MktUser::find($userId);

        if (!$model) {
            throw new Exception('invalid userId:' . $userId);
        }

        $mkt = $this->getMkt($model->app->platform)
            ->setApp((string)$model->app->app_id, (string)$model->app->app_secret);

        $refreshToken = $model->refresh_token;

        // 百度
        $this->bd($model->app->platform, $refreshToken, $userId);

        $userToken = $mkt->authRefreshToken($refreshToken);
        $userToken['user_id'] = $userId;

        return $this->saveUser($userToken);
    }

    /**
     * 授权用户信息
     * @param string $userId
     * @return bool
     * @throws Exception
     */
    public function authUserInfo(string $userId)
    {
        $model = MktUser::find($userId);

        if (!$model) {
            throw new Exception('invalid userId:' . $userId);
        }

        if ($model->app->platform == MktConst::PLATFORM_JL) {

            $mkt = $this->getMkt($model->app->platform);

            $userInfo = $mkt->setToken($model->access_token)->authUserInfo();

            return $this->saveUser($userInfo);
        } else {
            return false;
        }
    }

    /**
     * 授权帐户列表
     * @param string $userId
     * @throws Exception
     */
    public function accountList(string $userId)
    {
        $model = MktUser::find($userId);

        if (!$model) {
            throw new Exception('invalid userId:' . $userId);
        }

        // 快手
        if ($model->app->platform == MktConst::PLATFORM_KS) {
            $this->saveAccount([
                'user_id' => $userId,
                'account_id' => $userId,
                'is_valid' => 1
            ]);
            return;
        }

        $mkt = $this->getMkt($model->app->platform);

        $mkt->setApp((string)$model->app->app_id, (string)$model->app->app_secret);
        $mkt->setToken($model->access_token);

        $list = $mkt->authAccountGet();

        if (!empty($list) && is_array($list)) {
            foreach ($list as $value) {
                $value['user_id'] = $userId;
                $this->saveAccount($value);
            }
        }
    }

    /**
     * 广告主列表
     * @param string $accountId
     * @throws Exception
     */
    public function advertiserList(string $accountId)
    {
        $model = MktAccount::find($accountId);

        if (!$model) {
            throw new Exception('invalid accountId:' . $accountId);
        }

        $mkt = $this->getMkt($model->user->app->platform);
        $mkt->setToken($model->user->access_token);

        // 百度
        $this->bd($model->user->app->platform, $accountId, $model->account_name);

        $list = $mkt->advertiserGet($accountId);

        foreach ($list ?? [] as $value) {
            $value['account_id'] = $value['account_id'] ?? $accountId;
            $value['platform'] = $model->user->app->platform;

            Log::http('info', '【advertiserList】', [
                'service' => 'MktService',
                'func' =>  'saveAdvertiser',
                'args' => $value,
            ]);
            $this->saveAdvertiser($value);
        }
    }

    /**
     * 广告主预算
     * @param string $accountId
     * @param array $advertiserIds
     * @throws Exception
     */
    public function advertiserBudget(string $accountId, array $advertiserIds)
    {
        $model = MktAccount::find($accountId);

        if (!$model) {
            throw new Exception('invalid accountId:' . $accountId);
        }

        $mkt = $this->getMkt($model->user->app->platform);
        $mkt->setToken($model->user->access_token);

        $list = $mkt->advertiserBudget($advertiserIds);

        foreach ($list ?? [] as $data) {
            $report = ReportDailyAdvertiser::query()
                ->where('date', $data['date'])
                ->where('advertiser_id', $data['advertiser_id'])
                ->first();

            if ($report) {
                $report->fill($data)->save();
            }
        }
    }

    /**
     * 计划列表
     * @param string $advertiserId
     * @param array $planIds
     * @throws Exception
     */
    public function planList(string $advertiserId, $planIds = [])
    {
        $model = MktAdvertiser::find($advertiserId);

        if (!$model) {
            throw new Exception('invalid advertiserId:' . $advertiserId);
        }
        if ($model->platform == MktConst::PLATFORM_JL) {
            return;
        }

        $mkt = $this->getMkt($model->platform);
        $mkt->setToken($model->account->user->access_token);

        if ($model->platform === MktConst::PLATFORM_TX) {
            $mkt->setAppInfo($model->extra ?? []);
        }
        $this->oppo($mkt, $model);

        // 百度
        $this->bd($model->platform, $advertiserId, $model->advertiser_name);

        $page = 1;
        $cursor = '';
        $seenCursors = [];
        $pageSize = $model->platform == MktConst::PLATFORM_HW ? 50 : 100;
        $totalPage = 1;

        while ($page <= $totalPage) {

            try {
                $data = $model->platform === MktConst::PLATFORM_TX
                    ? $mkt->planGet($advertiserId, $page, $pageSize, $planIds, false, $cursor)
                    : $mkt->planGet($advertiserId, $page, $pageSize, $planIds, $model->extra['search'] ?? false);
                if ($model->platform === MktConst::PLATFORM_TX && $data['page_info']['total_page'] > $page) {
                    $cursor = $data['next_cursor'];
                    if (isset($seenCursors[$cursor])) {
                        throw new Exception('腾讯营销单元分页游标重复');
                    }
                    $seenCursors[$cursor] = true;
                }
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }

            $totalPage = $data['page_info']['total_page'];
            $page++;

            foreach ($data['list'] ?? [] as $value) {
                $value['app_code'] = $model->app_code;
                $this->savePlan($value);
            }
        }
    }

    /**
     * 广告列表
     * @param string $advertiserId
     * @param array $adIds
     * @throws Exception
     */
    public function adList(string $advertiserId, $adIds = [])
    {
        $model = MktAdvertiser::find($advertiserId);

        if (!$model) {
            throw new Exception('invalid advertiserId:' . $advertiserId);
        }
        if ($model->platform != MktConst::PLATFORM_JL) {
            return;
        }

        $mkt = $this->getMkt($model->platform);
        $mkt->setToken($model->account->user->access_token);

        $page = 1;
        $pageSize = 10;
        $totalPage = 1;

        while ($page <= $totalPage) {

            try {
                $data = $mkt->adGet($advertiserId, $page, $pageSize, $adIds);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }

            $totalPage = $data['page_info']['total_page'];
            $page++;

            foreach ($data['list'] ?? [] as $value) {
                $value['app_code'] = $model->app_code;
                $this->saveAd($value);
            }
        }
    }

    /**
     * 项目列表
     * @param string $advertiserId
     * @param array $projectIds
     * @throws Exception
     */
    public function projectList(string $advertiserId, $projectIds = [])
    {
        $model = MktAdvertiser::find($advertiserId);

        if (!$model) {
            throw new Exception('invalid advertiserId:' . $advertiserId);
        }
        if ($model->platform != MktConst::PLATFORM_JL) {
            return;
        }

        $mkt = $this->getMkt($model->platform);
        $mkt->setToken($model->account->user->access_token);

        $page = 1;
        $pageSize = 100;
        $totalPage = 1;

        while ($page <= $totalPage) {

            try {
                $data = $mkt->projectGet($advertiserId, $page, $pageSize, $projectIds);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }

            $totalPage = $data['page_info']['total_page'];
            $page++;

            foreach ($data['list'] ?? [] as $value) {
                $value['app_code'] = $model->app_code;
                $this->saveProject($value);
            }
        }
    }

    /**
     * 广告更新-状态
     * @param string $adId
     * @param string $putStatus
     * @return bool
     * @throws Exception
     */
    public function adUpdateStatus(string $adId, string $putStatus)
    {
        if (!in_array($putStatus, ['enable', 'disable'])) {
            throw new Exception('put_status error.');
        }
        $model = MktAd::find($adId);

        if (!$model) {
            throw new Exception('invalid adId:' . $adId);
        }

        $mkt = $this->getMkt($model->advertiser->platform);
        $mkt->setToken($model->advertiser->account->user->access_token);

        return $mkt->adUpdateStatus($model->advertiser_id, $adId, $putStatus);
    }


    /**
     * @param string $advertiserId
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    public function adBatchUpdateStatus(string $advertiserId, array $data)
    {
        Log::http('info', '【MKT-SERVICE', [
            'func' => 'adBatchUpdateStatus',
            'params' => func_get_args(),
            'stack' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
        ]);

        if (count($data) > 10 || count($data) < 1) {
            throw new Exception('批量限制1～10条');
        }
        $model = MktAdvertiser::find($advertiserId);

        if (!$model || $model->platform != MktConst::PLATFORM_JL) {
            throw new Exception('invalid advertiserId:' . $advertiserId);
        }

        $mkt = $this->getMkt($model->platform);
        $mkt->setToken($model->account->user->access_token);

        $return = $mkt->adBatchUpdateStatus($model->advertiser_id, $data);

        if (!empty($return['ad_ids'])) {
            go(function () use ($model, $data) {
                $this->adList($model->advertiser_id, array_column($data, 'ad_id'));
            });
        }

        return $return;
    }

    /**
     * 计划更新-状态
     * @param string $planId
     * @param string $putStatus [enable,disable]
     * @return bool
     * @throws Exception
     */
    public function planUpdateStatus(string $planId, string $putStatus)
    {
        if (!in_array($putStatus, ['enable', 'disable'])) {
            throw new Exception('put_status error.');
        }
        $model = MktPlan::find($planId);

        if (!$model) {
            throw new Exception('invalid planId:' . $planId);
        }

        if ($model->advertiser->platform === MktConst::PLATFORM_TX) {
            throw new Exception('腾讯营销投放配置仅支持查询');
        }

        $mkt = $this->getMkt($model->advertiser->platform);
        $mkt->setToken($model->advertiser->account->user->access_token);

        $advertiserId = $model->advertiser_id;
        // 百度
        $this->bd($model->advertiser->platform, $advertiserId, $model->advertiser->advertiser_name);

        return $mkt->planUpdateStatus($advertiserId, $planId, $putStatus, $model->advertiser->extra['search'] ?? false);
    }

    /**
     * 计划更新-预算
     * @param string $planId
     * @param float $budgetAmount
     * @return bool
     * @throws Exception
     */
    public function planUpdateBudget(string $planId, float $budgetAmount)
    {
        $model = MktPlan::find($planId);

        if (!$model) {
            throw new Exception('invalid planId:' . $planId);
        }

        if ($model->advertiser->platform === MktConst::PLATFORM_TX) {
            throw new Exception('腾讯营销投放配置仅支持查询');
        }

        $mkt = $this->getMkt($model->advertiser->platform);
        $mkt->setToken($model->advertiser->account->user->access_token);

        $advertiserId = $model->advertiser_id;
        // 百度
        $this->bd($model->advertiser->platform, $advertiserId, $model->advertiser->advertiser_name);

        return $mkt->planUpdateBudget($advertiserId, $planId, $budgetAmount, $model->advertiser->extra['search'] ?? false);
    }

    /**
     * 计划拉空当天时段
     * @param string $planId
     * @return bool
     * @throws Exception
     */
    public function planEmptyDayScheduleTime(string $planId)
    {
        $model = MktPlan::find($planId);

        if (!$model) {
            throw new Exception('invalid planId:' . $planId);
        }

        if ($model->advertiser->platform === MktConst::PLATFORM_TX) {
            throw new Exception('腾讯营销投放配置仅支持查询');
        }

        $mkt = $this->getMkt($model->advertiser->platform);
        $mkt->setToken($model->advertiser->account->user->access_token);

        // 星期
        $n = date('N');
        // 时间
        $h = date('H');

        $charNum = 1;
        if ($model->advertiser->platform == MktConst::PLATFORM_JL) {
            $charNum = 2;
        }

        $scheduleTime = '';
        for ($i = 1; $i <= 7; $i++) {
            for ($j = 1; $j <= 24; $j++) {
                $scheduleTime .= str_repeat($i == $n && $j >= $h ? '0' : '1', $charNum);
            }
        }
        // 运算
        $scheduleTime = $scheduleTime & $model->schedule_time;

        return $mkt->planUpdate($model->advertiser_id, $planId, [
            'schedule_time' => $scheduleTime
        ]);
    }

    /**
     * 日报表-广告主
     * @param string $advertiserId
     * @param string $date
     * @throws Exception
     */
    public function reportDailyAdvertiser(string $advertiserId, string $date)
    {
        $model = MktAdvertiser::find($advertiserId);

        if (!$model) {
            throw new Exception('invalid advertiserId:' . $advertiserId);
        }

        $mkt = $this->getMkt($model->platform);
        $mkt->setToken($model->account->user->access_token);
        if ($model->platform === MktConst::PLATFORM_TX) {
            $mkt->setAppInfo($model->extra ?? []);
        }
        $this->oppo($mkt, $model);

        // 百度
        $this->bd($model->platform, $advertiserId, $model->advertiser_name);

        if ($model->platform == MktConst::PLATFORM_JL) {
            $list = $mkt->reportAdvertiserGet($advertiserId, $date, 'daily', (string)$model->advertiser_name);
        } else {
            $list = $mkt->reportAdvertiserGet($advertiserId, $date, 'daily', $model->extra['search'] ?? false);
        }

        $data = $list[0] ?? [];

        if (!empty($data) && ($data['cost'] > 0 || $model->platform === MktConst::PLATFORM_TX)) {

            $report = ReportDailyAdvertiser::query()
                ->where('date', $data['date'])
                ->where('advertiser_id', $data['advertiser_id'])
                ->first();

            if (!$report) {
                $report = new ReportDailyAdvertiser();
            }

            if ($report->exists && $report->platform !== $model->platform && in_array(MktConst::PLATFORM_TX, [$model->platform, $report->platform], true)) {
                throw new Exception('腾讯广告主报表与已有平台数据冲突，拒绝覆盖');
            }
            $data['app_code'] = $model->app_code;
            $data['platform'] = $model->platform;

            $report->fill($data)->save();
        }
    }

    /**
     * 小时报表-广告主
     * @param string $advertiserId
     * @param string $date
     * @throws Exception
     */
    public function reportHourlyAdvertiser(string $advertiserId, string $date)
    {
        $model = MktAdvertiser::find($advertiserId);

        if (!$model) {
            throw new Exception('invalid advertiserId:' . $advertiserId);
        }

        $mkt = $this->getMkt($model->platform);
        $mkt->setToken($model->account->user->access_token);
        if ($model->platform === MktConst::PLATFORM_TX) {
            $mkt->setAppInfo($model->extra ?? []);
        }
        $this->oppo($mkt, $model);

        // 百度
        $this->bd($model->platform, $advertiserId, $model->advertiser_name);

        if ($model->platform == MktConst::PLATFORM_JL) {
            $list = $mkt->reportAdvertiserGet($advertiserId, $date, 'hourly', (string)$model->advertiser_name);
        } else {
            $list = $mkt->reportAdvertiserGet($advertiserId, $date, 'hourly', $model->extra['search'] ?? false);
        }

        if (empty($list) || (array_sum(array_column($list, 'cost')) <= 0 && $model->platform !== MktConst::PLATFORM_TX)) {
            return;
        }

        $keys = MktConst::HOUR_INFO_KEYS;
        $hourInfo = [];
        foreach ($list as $v) {
            foreach ($keys as $k) {
                $hourInfo[$v['hour']][$k] = $v[$k] ?? 0;
            }
        }
        $data = [
            'hour_info' => $hourInfo
        ];
        $model = ReportDailyAdvertiser::query()
            ->where('date', $date)
            ->where('advertiser_id', $list[0]['advertiser_id'] ?? 0)
            ->first();
        if ($model) {
            $model->fill($data)->save();
        }
    }

    /**
     * 日报表-项目
     * @param string $advertiserId
     * @param string $date
     * @throws Exception
     */
    public function reportDailyProject(string $advertiserId, string $date)
    {
        $model = MktAdvertiser::find($advertiserId);

        if (!$model) {
            throw new Exception('invalid advertiserId:' . $advertiserId);
        }
        if ($model->platform != MktConst::PLATFORM_JL) {
            return;
        }

        $mkt = $this->getMkt($model->platform);
        $mkt->setToken($model->account->user->access_token);

        $page = 1;
        $pageSize = 100;
        $totalPage = 1;

        while ($page <= $totalPage) {

            try {
                $data = $mkt->reportProjectGet($advertiserId, $date, [], 'daily', $page, $pageSize, (string)$model->advertiser_name);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }

            $totalPage = $data['page_info']['total_page'] ?? 0;
            $page++;

            foreach ($data['list'] ?? [] as $value) {

                if ($value['cost'] <= 0) {
                    continue;
                }

                $value['app_code'] = $model->app_code;
                $value['platform'] = $model->platform;

                $report = ReportDailyProject::query()
                    ->where('date', $value['date'])
                    ->where('project_id', $value['project_id'])
                    ->first();

                if (!$report) {
                    $report = new ReportDailyProject();
                }

                $report->fill($value)->save();
            }
        }
    }

    /**
     * 日报表-广告
     * @param string $advertiserId
     * @param string $date
     * @throws Exception
     */
    public function reportDailyAd(string $advertiserId, string $date)
    {
        $model = MktAdvertiser::find($advertiserId);

        if (!$model) {
            throw new Exception('invalid advertiserId:' . $advertiserId);
        }
        if ($model->platform != MktConst::PLATFORM_JL) {
            return;
        }

        $mkt = $this->getMkt($model->platform);
        $mkt->setToken($model->account->user->access_token);

        $page = 1;
        $pageSize = 100;
        $totalPage = 1;

        while ($page <= $totalPage) {

            try {
                $data = $mkt->reportAdGet($advertiserId, $date, [], 'daily', $page, $pageSize, (string)$model->advertiser_name);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }

            $totalPage = $data['page_info']['total_page'];
            $page++;

            foreach ($data['list'] ?? [] as $value) {

                if ($value['cost'] <= 0) {
                    continue;
                }

                $value['app_code'] = $model->app_code;
                $value['platform'] = $model->platform;

                $report = ReportDailyAd::query()
                    ->where('date', $value['date'])
                    ->where('ad_id', $value['ad_id'])
                    ->first();

                if (!$report) {
                    $report = new ReportDailyAd();
                }

                $report->fill($value)->save();
            }
        }
    }

    /**
     * 日报表-计划
     * @param string $advertiserId
     * @param string $date
     * @throws Exception
     */
    public function reportDailyPlan(string $advertiserId, string $date)
    {
        $model = MktAdvertiser::find($advertiserId);

        if (!$model) {
            throw new Exception('invalid advertiserId:' . $advertiserId);
        }
        if ($model->platform == MktConst::PLATFORM_JL) {
            return;
        }

        $mkt = $this->getMkt($model->platform);
        $mkt->setToken($model->account->user->access_token);
        if ($model->platform === MktConst::PLATFORM_TX) {
            $mkt->setAppInfo($model->extra ?? []);
        }
        $this->oppo($mkt, $model);

        // 百度
        $this->bd($model->platform, $advertiserId, $model->advertiser_name);

        $page = 1;
        $pageSize = 100;
        $totalPage = 1;

        while ($page <= $totalPage) {

            try {
                // vivo特殊处理
                $data = $model->platform == MktConst::PLATFORM_VI ?
                    $mkt->reportPlanGet($advertiserId, $date, [], 'daily', $page, $pageSize, $lastId ?? '')
                    : $mkt->reportPlanGet($advertiserId, $date, [], 'daily', $page, $pageSize, $model->extra['search'] ?? false);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }

            $totalPage = $data['page_info']['total_page'];
            $page++;

            $lastId = $data['lastId'] ?? '';

            foreach ($data['list'] ?? [] as $value) {

                if ($value['cost'] <= 0 && $model->platform !== MktConst::PLATFORM_TX) {
                    continue;
                }

                $value['app_code'] = $model->app_code;
                $value['platform'] = $model->platform;

                $report = ReportDailyPlan::query()
                    ->where('date', $value['date'])
                    ->where('plan_id', $value['plan_id'])
                    ->first();

                if (!$report) {
                    $report = new ReportDailyPlan();
                }

                if ($report->exists && in_array(MktConst::PLATFORM_TX, [$model->platform, $report->platform], true) && ((string)$report->advertiser_id !== (string)$advertiserId || $report->platform !== $model->platform)) {
                    throw new Exception('腾讯报表ID与已有平台数据冲突，拒绝覆盖');
                }
                $report->fill($value)->save();
            }
        }
    }


    /**
     * 小时报表-计划
     * @param string $advertiserId
     * @param string $date
     * @throws Exception
     */
    public function reportHourlyPlan(string $advertiserId, string $date)
    {
        $model = MktAdvertiser::find($advertiserId);

        if (!$model) {
            throw new Exception('invalid advertiserId:' . $advertiserId);
        }
        if ($model->platform == MktConst::PLATFORM_JL) {
            return;
        }

        $mkt = $this->getMkt($model->platform);
        $mkt->setToken($model->account->user->access_token);

        // 百度
        $this->bd($model->platform, $advertiserId, $model->advertiser_name);

        $page = 1;
        $pageSize = 100;
        $totalPage = 1;

        while ($page <= $totalPage) {

            try {
                $data = $mkt->reportPlanGet($advertiserId, $date, [], 'daily', $page, $pageSize, $model->extra['search'] ?? false);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }

            $totalPage = $data['page_info']['total_page'];
            $page++;

            foreach ($data['list'] ?? [] as $value) {

                if ($value['cost'] <= 0 && $model->platform !== MktConst::PLATFORM_TX) {
                    continue;
                }

                $value['app_code'] = $model->app_code;
                $value['platform'] = $model->platform;

                $report = ReportDailyPlan::query()
                    ->where('date', $value['date'])
                    ->where('plan_id', $value['plan_id'])
                    ->first();

                if (!$report) {
                    $report = new ReportDailyPlan();
                }

                if ($report->exists && in_array(MktConst::PLATFORM_TX, [$model->platform, $report->platform], true) && ((string)$report->advertiser_id !== (string)$advertiserId || $report->platform !== $model->platform)) {
                    throw new Exception('腾讯报表ID与已有平台数据冲突，拒绝覆盖');
                }
                $report->fill($value)->save();
            }
        }
    }

    public function getFund(string $advertiserId) {
        $model = MktAdvertiser::find($advertiserId);

        if (!$model) {
            throw new Exception('invalid advertiserId:' . $advertiserId);
        }
        if ($model->platform != MktConst::PLATFORM_JL) {
            return;
        }

        $mkt = $this->getMkt($model->platform);
        $mkt->setToken($model->account->user->access_token);

        return $mkt->getFund($advertiserId);
    }

    /**
     * 设置Token
     * @param string $token
     * @return $this
     */
    public function setToken(string $token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * 获取Token
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * 获取MKT开放平台应用信息
     * @param string $appId
     * @return MktApp|MktApp[]|Collection|Model|null
     * @throws Exception
     */
    public function getApp(string $appId)
    {
        if (empty($this->app)) {
            $this->app = MktApp::find($appId);
        }

        if (empty($this->app)) {
            throw new Exception("应用不存在");
        }

        return $this->app;
    }

    /**
     * 设置用户
     * @param string $userId
     * @return $this
     * @throws Exception
     */
    public function setUser(string $userId)
    {
        $data = MktUser::find($userId);

        if (empty($data)) {
            throw new Exception("用户不存在");
        }

        $this->user = $data;
        $this->token = $data->access_token;

        return $this;
    }

    /**
     * 授权用户
     * @return MktUser|MktUser[]|Collection|Model|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * 保存授权用户信息
     * @param array $data
     * @return bool
     */
    protected function saveUser(array $data)
    {
        $model = MktUser::find($data['user_id']);
        if ($model && isset($data['app_id']) && (string)$model->app_id !== (string)$data['app_id']) {
            $incomingApp = MktApp::find($data['app_id']);
            if (($model->app && $model->app->platform === MktConst::PLATFORM_TX) || ($incomingApp && $incomingApp->platform === MktConst::PLATFORM_TX)) {
                throw new Exception('腾讯授权用户ID与已有应用冲突，拒绝覆盖');
            }
        }

        if (!$model) {
            $model = new MktUser();
        }

        return $model->fill($data)->save();
    }

    /**
     * 保存帐户
     * @param array $data
     * @return bool
     */
    protected function saveAccount(array $data)
    {
        $model = MktAccount::find($data['account_id']);
        if ($model && (string)$model->user_id !== (string)$data['user_id']) {
            $incomingUser = MktUser::find($data['user_id']);
            $oldPlatform = $model->user && $model->user->app ? $model->user->app->platform : null;
            $newPlatform = $incomingUser && $incomingUser->app ? $incomingUser->app->platform : null;
            if ($oldPlatform === MktConst::PLATFORM_TX || $newPlatform === MktConst::PLATFORM_TX) {
                throw new Exception('腾讯账户ID与已有授权关系冲突，拒绝覆盖');
            }
        }

        if (!$model) {
            $model = new MktAccount();
        }

        return $model->fill($data)->save();
    }

    /**
     * 保存广告主
     * @param array $data
     * @return bool
     */
    protected function saveAdvertiser(array $data)
    {
        $model = MktAdvertiser::find($data['advertiser_id']);
        if ($model && $model->platform !== $data['platform'] && in_array(MktConst::PLATFORM_TX, [$model->platform, $data['platform']], true)) {
            throw new Exception('广告主ID与其他投放平台冲突，拒绝覆盖');
        }

        if ($model && $data['platform'] === MktConst::PLATFORM_TX && (string)$model->account_id !== (string)$data['account_id']) {
            throw new Exception('腾讯广告主ID与已有账户绑定冲突，拒绝覆盖');
        }
        if ($model && !empty($model->app_code) && $model->advertiser_name == $data['advertiser_name']) {
            return true;
        }

        $model = $model ?? new MktAdvertiser();

//        $isKeyAction = strstr($data['advertiser_name'] , '关键行为') !== false ? true : false;
        // 暂时仅有关键行为
        $isKeyAction = false;
        // 默认自定义关键行为策略
        $defaultActionValue = json_encode(MktConst::ACTION_VALUE_DEFAULT);

        $modeData = [
            'app_code' => !empty($model->app_code) ? $model->app_code : $this->getAppCodeByAdvertiserName($data['advertiser_name']),
            'agency_name' => $this->getAgencyByAdvertiserName($data['advertiser_name']),
            'launch_type' => $model->launch_type ?? ($isKeyAction ? 1 : 0),
            'active_type' => $model->active_type ?? ($isKeyAction ? 2 : 2),
            'active_value' => $model->active_value ?? null,
            'action_type' => $model->action_type ?? ($isKeyAction ? 3 : 0),
            'keep_type' => $model->keep_type ?? ($isKeyAction ? 0 : 1),
            'action_value' => $model->action_value ?? ($isKeyAction ? $defaultActionValue : null),
            'status' => strstr($data['advertiser_name'], '停') ? 0 :
                (($model->advertiser_name && strstr($model->advertiser_name, '停')) ? 1 :
                    ($model->status ?? 1))
//            'action_revenue' => $model->action_revenue ?? ($isKeyAction ? 70 : 0)
        ];

        return $model->fill(array_merge($data, $modeData))->save();
    }

    /**
     * 保存计划
     * @param array $data
     * @return bool
     */
    protected function savePlan(array $data)
    {
        $model = MktPlan::find($data['plan_id']);
        if ($model && (string)$model->advertiser_id !== (string)$data['advertiser_id']) {
            $incoming = MktAdvertiser::find($data['advertiser_id']);
            if (($incoming && $incoming->platform === MktConst::PLATFORM_TX) || ($model->advertiser && $model->advertiser->platform === MktConst::PLATFORM_TX)) {
                throw new Exception('腾讯计划ID与已有广告主计划冲突，拒绝覆盖');
            }
        }

        if (!$model) {
            $model = new MktPlan();
        }

        return $model->fill($data)->save();
    }

    /**
     * 保存项目
     * @param array $data
     * @return bool
     */
    protected function saveProject(array $data)
    {
        $model = MktProject::find($data['project_id']);

        if (!$model) {
            $model = new MktProject();
        }

        return $model->fill($data)->save();
    }

    /**
     * 保存项目
     * @param array $data
     * @return bool
     */
    protected function saveAd(array $data)
    {
        $model = MktAd::find($data['ad_id']);

        if (!$model) {
            $model = new MktAd();
        }

        return $model->fill($data)->save();
    }


    /**
     * 获取API实例
     * @param string $platform
     * @return MktAbstract
     * @throws Exception
     */
    public function getMkt(string $platform)
    {
        $platform = strtolower($platform);

        if (!isset(MktConst::PLATFORM_SERVICE[$platform])) {
            throw new Exception('服务不存在');
        }

        return make(MktConst::PLATFORM_SERVICE[$platform]);
    }

    /**
     * 根据广告主名称获取应用标识
     * @param string $advertiserName
     * @return string
     * @throws Exception
     */
    protected function getAppCodeByAdvertiserName(string $advertiserName)
    {
        if (empty($advertiserName)) {
            return '';
        }
        $app = App::query()->pluck('app_name', 'app_code')->toArray();

        $appCode = '';
        foreach ($app as $code => $name) {
            if (stristr($advertiserName, $name)) {
                $appCode = $code;
            }
        }
        return $appCode;
    }

    /**
     * @param string $advertiserName
     * @return string
     * @throws Exception
     */
    protected function getAgencyByAdvertiserName(string $advertiserName)
    {
        if (empty($advertiserName)) {
            return '';
        }

        $arr = explode('_', $advertiserName);

        $agencyName = '';
        if (count($arr) >= 3) {
            $agencyName = $arr[1];
        }

        return $agencyName;
    }

    /**
     * 自投应用
     * @return array
     */
    public function appListPutSelf()
    {
        return (new App())->appList([], [], [AppConst::APP_PUT_STATUS_SELF]);
    }

    public function appListPut()
    {
        return (new App())->appList([], [], [AppConst::APP_PUT_STATUS_SELF, AppConst::APP_PUT_STATUS_AGENCY]);
    }

    /**
     * OPPO特殊处理
     * @param $mkt
     * @param $model
     */
    protected function oppo($mkt, $model)
    {
        if ($model->platform == MktConst::PLATFORM_OP) {
            $extra = $model->extra;
            $mkt->setApp($extra['sign_id'] ?? '', $extra['sign_key'] ?? '');
        }
    }

    /**
     * 百度
     * @param $platform
     * @param $str1
     * @param $str2
     */
    protected function bd($platform, &$str1, $str2)
    {
        if ($platform == MktConst::PLATFORM_BD) {
            $str1 .= MktConst::PLATFORM_BD_DELIMITER . $str2;
        }
    }
}
