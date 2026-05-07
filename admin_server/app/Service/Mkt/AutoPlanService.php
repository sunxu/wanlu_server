<?php

declare(strict_types=1);

namespace App\Service\Mkt;

use App\Constants\AppConst;
use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\App;
use App\Model\Mkt\MktAd;
use App\Model\Mkt\MktAdvertiser;
use App\Model\Mkt\MktAutoPlan;
use App\Model\Mkt\MktAutoPlanLog;
use App\Model\Mkt\ReportDailyAd;
use App\Utils\Ding;
use App\Utils\Log;
use App\Utils\RedisManager;
use Hyperf\DbConnection\Db;

class AutoPlanService
{
    protected $executeLog = [];

    public function savePlan(string $appCode, array $params)
    {
        Db::transaction(function () use ($appCode, $params) {
            $thresholdKeys = collect(data_get($params, 'threshold'))->keys()->toArray();
            $planModel = new MktAutoPlan($params);
            $planModel->advertisers = $this->getAdvertiserIds($appCode, 0, data_get($params, 'advertiser_ids', []), $thresholdKeys);
            $planModel->app_code = $appCode;
            $planModel->plan_enable = 1;// 开启
            $planModel->plan_status = 0;// 待运行
            $planModel->next_start_time = $this->getNextStartTime(data_get($params, 'execute_rule'));
            $planModel->saveOrFail();
        });
    }

    // 计算下次运行时间
    protected function getNextStartTime(array $executeRule)
    {
        switch (data_get($executeRule, 'type')) {
            case 'hour':
                $step = data_get($executeRule, 'step');
                return strtotime(date("Y-m-d H:i:00")) + 60 * 60 * $step;
        }
        throw new BusinessException(ErrorCode::PARAMS_ERROR, '不存在的执行频率类型');
    }

    /**
     * 判断是广告主是否存在与已有的计划阈值冲突 存在则覆盖计划
     * @param string $appCode
     * @param array $advertiserIds
     * @param array $thresholdKeys
     * @return array
     * @throws \Throwable
     */
    protected function getAdvertiserIds(string $appCode, $planId, ?array $advertiserIds, array $thresholdKeys)
    {
        // 如果为空则为全部广告主 全部广告主不做覆盖判断
        if (!$advertiserIds) {
            return ['isAll' => 1, 'ids' => []];
        }
        // 判断是广告主是否存在与已有的计划阈值冲突 存在则覆盖计划
        $conflictPlans = $this->checkPlanConflict($appCode, $planId, $advertiserIds, $thresholdKeys);
        if ($conflictPlans) {
            foreach ($conflictPlans as $conflictPlan) {
                $planModel = MktAutoPlan::find(data_get($conflictPlan, 'plan_id'));
                $ids = collect($planModel->advertisers['ids'])->diff($advertiserIds)->values()->toArray();
                if ($ids) {
                    $planModel->advertisers = [
                        'isAll' => 0,
                        'ids' => collect($planModel->advertisers['ids'])->diff($advertiserIds)->values()->toArray()
                    ];
                } else {
                    $planModel->plan_enable = 0;
                }
                $planModel->saveOrFail();
            }
        }

        return ['isAll' => 0, 'ids' => $advertiserIds];
    }

    /**
     * 广告主是否存在与已有的计划阈值冲突
     * @param string $appCode
     * @param array $advertiserIds
     * @return array
     */
    public function checkPlanConflict(string $appCode, $planId, ?array $advertiserIds, array $thresholdKeys)
    {
        // 全选广告主 不做判断
        if (!$advertiserIds) {
            return [];
        }
        $whereSql = "";
        foreach ($advertiserIds as $advertiserId) {
            $whereSql .= "JSON_CONTAINS(advertisers->'$.ids','{$advertiserId}') or ";
        }
        $whereSql = substr($whereSql, 0, -3);
        $plans = MktAutoPlan::select(['plan_id', 'plan_name', 'advertisers', 'threshold'])
            ->where('app_code', $appCode)
            ->where('plan_enable', 1)
            ->when(!empty($whereSql), function ($query) use ($whereSql) {
                $query->whereRaw("({$whereSql})");
            })
            ->when($planId, function ($query, $planId) {
                $query->where('plan_id', '<>', $planId);
            })
            ->get()->all();
        $advertisersInfo = MktAdvertiser::select(['advertiser_id', 'advertiser_name'])
            ->when($advertiserIds, function ($query, $advertiserIds) {
                $query->whereIn('advertiser_id', $advertiserIds);
            })
            ->where('app_code', $appCode)
            ->get()->all();
        $result = [];
        foreach ($plans as $plan) {
            $thresholdKeysConflict = collect(data_get($plan, 'threshold'))->keys()->intersect($thresholdKeys)->toArray();
            if (!$thresholdKeysConflict) {
                continue;
            }
            $advertisers = collect(data_get($plan, 'advertisers.ids'))->intersect($advertiserIds)->toArray();
            $result[] = [
                'plan_id' => data_get($plan, 'plan_id'),
                'plan_name' => data_get($plan, 'plan_name'),
                'advertisers' => collect($advertisersInfo)->whereIn('advertiser_id', $advertisers)->toArray(),
            ];
        }
        return $result;
    }

    public function planList(string $appCode, array $params)
    {
        $planListPaginate = MktAutoPlan::select([
            'plan_id', 'plan_name', 'plan_enable', 'plan_status', 'last_start_time', 'last_end_time', 'next_start_time',
            'execute_times', 'created_time', 'updated_time', 'advertisers', 'threshold', 'last_execute_result'
        ])
            ->where('app_code', $appCode)
            ->when(data_get($params, 'advertiser_ids'), function ($query, $advertiserIds) {
                $whereSql = "advertisers->'$.isAll' = 1 or ";
                foreach ($advertiserIds as $advertiserId) {
                    $whereSql .= "JSON_CONTAINS(advertisers->'$.ids','{$advertiserId}') or ";
                }
                $whereSql = substr($whereSql, 0, -3);
                $query->whereRaw("({$whereSql})");
            })
            ->when(data_get($params, 'plan_name'), function ($query, $planName) {
                $query->where('plan_name', 'like', "%{$planName}%");
            })
            ->when(data_get($params, 'plan_id'), function ($query, $planId) {
                $query->where('plan_id', $planId);
            })
            ->when(data_get($params, 'plan_enable'), function ($query, $planEnable) {
                $query->where('plan_enable', $planEnable);
            })
            ->orderBy('created_time', 'desc')
            ->orderBy('updated_time', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)));
        $advertiserList = MktAdvertiser::select(['advertiser_id', 'advertiser_name'])
            ->where('app_code', $appCode)
            ->get()->all();
        $planList = collect($planListPaginate->items())->map(function ($item) use ($advertiserList) {
            $advertiserInfo = $advertiserList;
            if (data_get($item, 'advertisers.isAll') != 1) {
                $advertiserIds = data_get($item, 'advertisers.ids');
                $advertiserInfo = collect($advertiserList)->whereIn('advertiser_id', $advertiserIds)->values()->toArray();
            }
            data_set($item, 'advertiser_info', $advertiserInfo);
            return $item;
        });
        $planListPaginate->setCollection($planList);
        return $planListPaginate;
    }

    public function planInfo(int $planId)
    {
        return MktAutoPlan::findOrFail($planId);
    }

    public function enablePlan(int $planId)
    {
        Db::transaction(function () use ($planId) {
            $planModel = MktAutoPlan::findOrFail($planId);
            $planModel->plan_enable = $planModel->plan_enable ^ 1 ^ 0;
            $planModel->next_start_time = $this->getNextStartTime($planModel->execute_rule);
            if ($planModel->plan_enable == 1) {
                $thresholdKeys = collect($planModel->threshold)->keys()->toArray();
                $this->getAdvertiserIds($planModel->app_code, $planId, $planModel->advertisers['ids'], $thresholdKeys);
            }
            $planModel->saveOrFail();
        });
    }

    public function editPlan(string $appCode, int $planId, array $params)
    {
        Db::transaction(function () use ($appCode, $planId, $params) {
            $thresholdKeys = collect(data_get($params, 'threshold'))->keys()->toArray();
            $planModel = MktAutoPlan::findOrFail($planId);
            $planModel->advertisers = $this->getAdvertiserIds($appCode, $planId, data_get($params, 'advertiser_ids', []), $thresholdKeys);
            $planModel->plan_name = data_get($params, 'plan_name');
            $planModel->threshold = data_get($params, 'threshold');
            $planModel->execute_rule = data_get($params, 'execute_rule');
            $planModel->execute_operate = data_get($params, 'execute_operate');
            $planModel->next_start_time = $this->getNextStartTime(data_get($params, 'execute_rule'));
            $planModel->saveOrFail();
        });
    }

    public function schedulePlan()
    {
        $appCodes = App::whereIn('ad_status', [AppConst::APP_PUT_STATUS_SELF, AppConst::APP_PUT_STATUS_AGENCY])->where('type', AppConst::APP_TYPE_TOOLS)
            ->pluck('app_code')->toArray();
        $plans = MktAutoPlan::where('plan_enable', 1)
            ->where('plan_status', 0)
            ->where('next_start_time', '<=', time())
            ->whereIn('app_code', $appCodes)
            ->get()->all();
        $planIds = collect($plans)->pluck('plan_id')->toArray();
        MktAutoPlan::whereIn('plan_id', $planIds)->update([
            'plan_status' => 1,
        ]);
        foreach ($plans as $plan) {
            try {
                $startTime = time();
                $planId = data_get($plan, 'plan_id');
                Db::transaction(function () use ($plan, $startTime, $planId) {
                    $threshold = data_get($plan, 'threshold');
                    $appCode = data_get($plan, 'app_code');
                    $advertiserIds = data_get($plan, 'advertisers.ids');
                    if (data_get($plan, 'advertisers.isAll') == 1) {
                        $advertiserIds = MktAdvertiser::select(['advertiser_id'])
                            ->where('app_code', data_get($plan, 'app_code'))
                            ->where('status', 1)
                            ->get()->pluck('advertiser_id')->all();
                    }
                    $adIds = ReportDailyAd::select('ad_id')
                        ->whereIn('advertiser_id', $advertiserIds)
                        ->where('date', date('Y-m-d'))
                        ->get()->pluck('ad_id')->all();
                    foreach ($adIds as $adId) {
                        $resultBool = $this->judgeAdvertiserThresholdReached($appCode, $planId, $adId, $threshold);
                        if ($resultBool) {
                            $execute = data_get($plan, 'execute_operate');
                            $this->schedulePlanExecute($planId, $adId, $execute);
                        }
                        $this->schedulePlanLogResult($planId, $adId, $resultBool, $execute ?? '');
                    }
                    $planModel = MktAutoPlan::findOrFail($planId);
                    $planModel->plan_status = 0;
                    $planModel->last_start_time = $startTime;
                    $planModel->last_end_time = time();
                    $planModel->execute_times++;
                    $planModel->next_start_time = $this->getNextStartTime(data_get($plan, 'execute_rule'));
                    $planModel->saveOrFail();
                    // 记录日志
                    $this->saveSchedulePlanLog($adIds, $plan, $startTime);
                });
            } catch (\Exception $exception) {
                Log::system('error', '任务执行失败', [
                    'plan' => $plan,
                    'file' => $exception->getFile(),
                    'message' => $exception->getMessage(),
                    'line' => $exception->getLine()
                ]);
                $planModel = MktAutoPlan::findOrFail($planId);
                $planModel->plan_status = 0;
                $planModel->last_start_time = $startTime;
                $planModel->last_end_time = time();
                $planModel->execute_times++;
                $planModel->next_start_time = $this->getNextStartTime(data_get($plan, 'execute_rule'));
                $planModel->last_execute_result = 0;
                $planModel->saveOrFail();
            }
        }
    }

    // 执行操作 并发送dingding
    protected function schedulePlanExecute($planId, $adId, $execute)
    {
        try {
            switch ($execute){
                case 'close_plan':
                    $comment = '关闭计划';
                    $bool = make(MktService::class)->adUpdateStatus($adId, 'disable');
                    break;
            }
        }catch (\Exception $exception){
            $comment .= ":执行失败";
            $bool = false;
            Log::system('error', $comment, [
                'plan' => $planId,
                'file' => $exception->getFile(),
                'message' => $exception->getMessage(),
                'line' => $exception->getLine()
            ]);
        }
        // 记录日志
        $this->schedulePlanExecuteResult($planId, $adId, $execute, $bool);
        $adInfo = MktAd::select(['advertiser_id', 'put_status', 'ad_name'])
            ->where('ad_id', $adId)
            ->first();
        if (data_get($adInfo, 'put_status') != 1) {
            return;
        }
        $planModel = MktAutoPlan::findOrFail($planId);
        $advertiserId = data_get($adInfo, 'advertiser_id');
        $adName = data_get($adInfo, 'ad_name');
        $message = "**监测计划规则:{$planId}|{$planModel->plan_name}**\n\n";
        $message .= "**广告主:{$advertiserId}**\n\n";
        $message .= "**广告:{$adId}-{$adName}**\n\n";
        $message .= "**执行时间:" . date("Y-m-d H:i:s") . "**\n\n";
        $message .= "**执行操作:{$comment}**\n\n";
        $message .= "**执行结果:";
        $message .= $bool ? "成功" : "失败";
        $message .= "**";
        make(Ding::class)
            ->setRobotCode('mkt_put')
            ->pushMarkdown('投放监测', $message);
    }

    // 保存执行日志
    protected function saveSchedulePlanLog($adIds, $plan, $startTime)
    {
        $planId = data_get($plan, 'plan_id');
//        $redis = RedisManager::get();
        $logRedisKey = "autoPlan-{$planId}-result";
        $resultRedisKey = "autoPlan-{$planId}";
        $redisExecuteKey = "autoPlan-{$planId}-result-execute";
//        $logText = $redis->get($logRedisKey);
        $logText = $this->executeLog[$logRedisKey];
//        $logResult = $redis->get($resultRedisKey);
        $logResult = $this->executeLog[$resultRedisKey];
//        $executeResult = $redis->get($redisExecuteKey);
        $executeResult = $this->executeLog[$redisExecuteKey];
        $log = [
            'advertisers' => $adIds,
//            'log' => json_decode($logText ?: '[]', true),
//            'result' => json_decode($logResult ?: '[]', true),
//            'execute_result' => json_decode($executeResult ?: '[]', true),
            'log' => $logText,
            'result' => $logResult,
            'execute_result' => $executeResult
        ];
        $logModel = new MktAutoPlanLog();
        $logModel->plan_id = $planId;
        $logModel->begin_time = $startTime;
        $logModel->end_time = time();
        $logModel->schedule_log = $log;
        $logModel->plan_snapshoot = $plan;
        $logModel->saveOrFail();
        $this->executeLog = [];
//        $redis->del($logRedisKey);
//        $redis->del($resultRedisKey);
//        $redis->del($redisExecuteKey);
    }

    protected function schedulePlanExecuteResult($planId, $adId, $execute, $bool)
    {
//        $redis = RedisManager::get();
        $redisKey = "autoPlan-{$planId}-result-execute";
        $log = [];
        if (isset($this->executeLog[$redisKey])) {
//        if ($redis->exists($redisKey)) {
//            $log = $redis->get($redisKey);
//            $log = json_decode($log, true);
            $log = $this->executeLog[$redisKey];
        }
        $log[] = [
            'id' => $adId,
            'execute_result' => $bool,
            'execute' => $execute
        ];
        $this->executeLog[$redisKey] = $log;
//        $redis->set($redisKey, json_encode($log));
//        $redis->expire($redisKey, 60 * 10);
    }

    protected function schedulePlanLog(int $planId, $adId, $text)
    {
//        $redis = RedisManager::get();
        $redisKey = "autoPlan-{$planId}";
        $log = [];
        if (isset($this->executeLog[$redisKey])) {
//        if ($redis->exists($redisKey)) {
//            $log = $redis->get($redisKey);
//            $log = json_decode($log, true);
            $log = $this->executeLog[$redisKey];
        }
        $log[] = [
            'id' => $adId,
            'text' => $text,
        ];
        $this->executeLog[$redisKey] = $log;
//        $redis->set($redisKey, json_encode($log));
//        $redis->expire($redisKey, 60 * 10);
    }

    protected function schedulePlanLogResult(int $planId, $adId, $result, $execute = '')
    {
//        $redis = RedisManager::get();
        $redisKey = "autoPlan-{$planId}-result";
        $log = [];
        if (isset($this->executeLog[$redisKey])) {
//        if ($redis->exists($redisKey)) {
//            $log = $redis->get($redisKey);
//            $log = json_decode($log, true);
            $log = $this->executeLog[$redisKey];
        }
        $log[] = [
            'id' => $adId,
            'result' => $result,
            'execute' => $execute
        ];
        $this->executeLog[$redisKey] = $log;
//        $redis->set($redisKey, json_encode($log));
//        $redis->expire($redisKey, 60 * 10);
    }

    protected function judgeAdvertiserThresholdReached($appCode, $planId, $adId, $threshold): bool
    {
        $resultBool = false;
        foreach ($threshold as $key => $value) {
            switch ($key) {
                case 'cost':
                case 'active_cost':
                case 'convert_cost':
                case 'key_action_rate':
                case 'key_action_cost':
                case 'convert_rate':
                    $columnValue = $this->getAdvertiserColumn($appCode, $adId, $key);
                    $resultBool = judge($columnValue, data_get($value, 'value'), data_get($value, 'operator'));
                    break;
                case 'arpu':
                    $arpu = $this->getAdvertiserArpu($appCode, $adId);
                    $resultBool = judge($arpu, data_get($value, 'value'), data_get($value, 'operator'));
                    break;
                case 'roi':
                    $roi = $this->getAdvertiserRoi($appCode, $adId);
                    $resultBool = judge($roi, data_get($value, 'value'), data_get($value, 'operator'));
                    break;
                default :
                    $resultBool = false;
            }
            // 记录日志
            $this->schedulePlanLog($planId, $adId, "{$key}:$columnValue;judge:" . json_encode($value));
            if (!$resultBool) {
                return $resultBool;
            }
        }

        return $resultBool;
    }

    protected function getAdvertiserColumn($appCode, $adId, $column)
    {
        $advertiserInfo = ReportDailyAd::select($column)
            ->where('ad_id', $adId)
            ->where('app_code', $appCode)
            ->where('date', date("Y-m-d"))
            ->first();
        return data_get($advertiserInfo, $column);
    }

    protected function getAdvertiserArpu($appCode, $adId)
    {
        $advertiserInfo = Db::table('report_daily_ad as rdadv')
            ->leftJoin('report_daily_ad_ext as rdadve', 'rdadve.id', '=', 'rdadv.id')
            ->select(['rdadve.install_num', 'rdadve.install_revenue'])
            ->where('rdadv.ad_id', $adId)
            ->where('rdadv.app_code', $appCode)
            ->where('rdadv.date', date("Y-m-d"))
            ->first();

        return safe_div(data_get($advertiserInfo, 'install_revenue'), data_get($advertiserInfo, 'install_num'));
    }

    protected function getAdvertiserRoi($appCode, $adId)
    {
        $advertiserInfo = Db::table('report_daily_ad as rdadv')
            ->leftJoin('report_daily_ad_ext as rdadve', 'rdadve.id', '=', 'rdadv.id')
            ->select(['rdadv.cost', 'rdadve.install_revenue'])
            ->where('rdadv.ad_id', $adId)
            ->where('rdadv.app_code', $appCode)
            ->where('rdadv.date', date("Y-m-d"))
            ->first();

        return safe_div(data_get($advertiserInfo, 'install_revenue'), data_get($advertiserInfo, 'cost'));
    }

    public function planLogs(int $planId, array $params)
    {
        return MktAutoPlanLog::select([
            'log_id', 'begin_time', 'end_time', 'created_time'
        ])
            ->where('plan_id', $planId)
            ->orderBy('created_time', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)));
    }

    public function planLogInfo(int $logId)
    {
        return MktAutoPlanLog::findOrFail($logId);
    }
}