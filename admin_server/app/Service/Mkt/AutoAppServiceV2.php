<?php

declare(strict_types=1);

namespace App\Service\Mkt;

use App\Constants\MktConst;
use App\Model\App;
use App\Model\Mkt\MktAutoAppV2;
use App\Model\Mkt\MktAutoAppLogV2;
use App\Model\Mkt\ReportDailyAdvertiser;
use App\Utils\Lark;
use Hyperf\Cache\Cache;
use Hyperf\DbConnection\Db;

class AutoAppServiceV2
{
    protected $executeOperateCommentArr = [
        'pause-ad' => '暂停计划',
        'empty-day' => '拉空当天时段',
        'empty-all' => '拉空全时段',
        'cancel-empty-day' => '取消拉空当天时段',

        'close_plan' => '关闭',
        'open_plan' => '开启',
    ];

    public function savePlan(array $params)
    {
        Db::transaction(function () use ($params) {
            $planModel = new MktAutoAppV2($params);
            $planModel->app_code = data_get($params, 'app_code');
            $planModel->plan_enable = 1;// 开启
            $planModel->saveOrFail();
        });
    }

    public function planList(array $params)
    {
        $planListPaginate = MktAutoAppV2::select([
            'plan_id', 'plan_name', 'plan_enable', 'created_time', 'updated_time', 'app_code', 'threshold', 'execute_operate'
        ])
            ->when(data_get($params, 'app_code'), function ($query, $appCode) {
                $query->whereRaw("JSON_CONTAINS(app_code,'\"{$appCode}\"')");
            })
            ->when(data_get($params, 'plan_name'), function ($query, $planName) {
                $query->where('plan_name', 'like', "%{$planName}%");
            })
            ->when(data_get($params, 'plan_id'), function ($query, $planId) {
                $query->where('plan_id', $planId);
            })
            ->when(in_array(data_get($params, 'plan_enable'), [0, 1], true), function ($query) use ($params) {
                $query->where('plan_enable', data_get($params, 'plan_enable'));
            })
            ->orderBy('created_time', 'desc')
            ->orderBy('updated_time', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)));
        $appList = (new App)->appList();
        $planList = collect($planListPaginate->items())->map(function ($item) use ($appList) {
            $appName = [];
            foreach (data_get($item, 'app_code') as $code) {
                if (isset($appList[$code])) {
                    $appName[$code] = $appList[$code];
                }
            }
            data_set($item, 'app_name', implode(',', $appName));
            return $item;
        });
        $planListPaginate->setCollection($planList);
        return $planListPaginate;
    }

    public function planInfo(int $planId)
    {
        return MktAutoAppV2::findOrFail($planId);
    }

    public function enablePlan(int $planId)
    {
        Db::transaction(function () use ($planId) {
            $planModel = MktAutoAppV2::findOrFail($planId);
            $planModel->plan_enable = $planModel->plan_enable ^ 1 ^ 0;
            $planModel->saveOrFail();
        });
    }

    public function editPlan(int $planId, array $params)
    {
        Db::transaction(function () use ($planId, $params) {
            $planModel = MktAutoAppV2::findOrFail($planId);
            $planModel->plan_name = data_get($params, 'plan_name');
            $planModel->app_code = data_get($params, 'app_code');
            $planModel->threshold = data_get($params, 'threshold');
            $planModel->execute_operate = data_get($params, 'execute_operate');
            $planModel->saveOrFail();
        });
    }

    public function schedulePlan()
    {
        $appCodes = ReportDailyAdvertiser::where('date', date('Y-m-d'))->pluck('app_code')->toArray();
        $plans = MktAutoAppV2::where('plan_enable', 1)
            ->get()
            ->toArray();
        $apps = (new App())->appList();
        foreach ($plans as $plan) {
            foreach (data_get($plan, 'app_code') as $appCode) {
                if (!in_array($appCode, $appCodes)) {
                    continue;
                }
                $plan['app_code'] = $appCode;
                $plan['app_name'] = $apps[$appCode] ?? '';
                $this->executePlan($plan);
            }
        }
    }

    // 执行操作
    protected function schedulePlanExecute(array $plan, $batchAdInfo)
    {
        $mkt = make(MktService::class);
        $execute = data_get($plan, 'execute_operate');
        $result = [
            'ad_info' => $batchAdInfo,
            'execute' => [],
        ];
        $startTime = time();
        switch ($execute) {
            case 'close_plan':
            case 'pause-ad':
                $funcName = 'adBatchUpdateStatus';
                foreach ($batchAdInfo as $advertiserId => $adInfo) {
                    $data = [];
                    foreach ($adInfo as $ad) {
                        $data[] = [
                            'ad_id' => data_get($ad, 'ad_id'),
                            'status' => 'disable'
                        ];
                    }
                    $newData = array_chunk($data, 10);
                    foreach ($newData as $newAd) {
                        $r = $this->adBatchData($mkt, $funcName, $plan, $advertiserId, $newAd);
                        if (!empty($r)) {
                            $result['execute'][] = $r;
                        }
                    }
                }
                break;
            case 'open_plan':
                $funcName = 'adBatchUpdateStatus';
                foreach ($batchAdInfo as $advertiserId => $adInfo) {
                    $data = [];
                    foreach ($adInfo as $ad) {
                        $data[] = [
                            'ad_id' => data_get($ad, 'ad_id'),
                            'status' => 'enable'
                        ];
                    }
                    $newData = array_chunk($data, 10);
                    foreach ($newData as $newAd) {
                        $r = $this->adBatchData($mkt, $funcName, $plan, $advertiserId, $newAd);
                        if (!empty($r)) {
                            $result['execute'][] = $r;
                        }
                    }
                }
                break;
            case 'cancel-empty-day':
            case 'empty-day':
            case 'empty-all':
                $funcName = 'adBatchUpdateScheduleTime';
                foreach ($batchAdInfo as $advertiserId => $adInfo) {
                    $data = [];
                    foreach ($adInfo as $ad) {
                        // 过滤
                        if (data_get($ad, 'put_status') != 1 || $this->judgeScheduleTime($execute, data_get($ad, 'schedule_time'))) {
                            continue;
                        }
                        $data[] = [
                            'ad_id' => data_get($ad, 'ad_id'),
                            'schedule_time' => $this->getScheduleTime($execute, data_get($ad, 'schedule_time'))
                        ];
                    }
                    $newData = array_chunk($data, 10);
                    foreach ($newData as $newAd) {
                        $r = $this->adBatchData($mkt, $funcName, $plan, $advertiserId, $newAd);
                        if (!empty($r)) {
                            $result['execute'][] = $r;
                        }
                    }
                }
                break;
        }

        $logModel = new MktAutoAppLogV2();
        $logModel->plan_id = data_get($plan, 'plan_id');
        $logModel->begin_time = $startTime;
        $logModel->end_time = time();
        $logModel->schedule_log = $result;
        $logModel->plan_snapshoot = $plan;
        $logModel->saveOrFail();

        $errors = [];
        $adIds = [];
        foreach ($result['execute'] as $item) {
            if (!empty($item['errors'])) {
                $errors[] = $item['errors'];
            }
            if (!empty($item['ad_ids'])) {
                $adIds[] = $item['ad_ids'];
            }
        }
        if (empty($adIds) && empty($errors)) {
            return;
        }

        $content = [
            [
                [
                    'tag' => 'text',
                    'text' => "{$plan['app_name']}({$plan['app_code']})|{$plan['plan_name']}({$plan['plan_id']})-" . ($this->executeOperateCommentArr[$execute] ?? '')
                ],
            ]
        ];
        if (!empty($adIds)) {
            $content[] = [
                [
                    'tag' => 'text',
                    'text' => '成功：' . json_encode($adIds, 320)
                ]
            ];
        }
        if (!empty($errors)) {
            $content[] = [
                [
                    'tag' => 'text',
                    'text' => '失败：' . json_encode($errors, 320)
                ],
            ];
        }
        make(Lark::class)
            ->setRobotCode('mkt_put')
            ->pushRichText('监测计划', $content);
    }

    protected function judgeAdvertiserThresholdReached($adInfo, $threshold): bool
    {
        $resultBool = false;
        foreach ($threshold as $key => $value) {
            if (!in_array($key, ['cost', 'active_cost', 'key_action_cost',
                'key_action_rate', 'arpu', 'roi', 'attribution_app_24h_roi']))
            {
                continue;
            }

            $resultBool = judge(data_get($adInfo, $key, 0), data_get($value, 'value'), data_get($value, 'operator'));
            if (!$resultBool) {
                break;
            }
        }

        return $resultBool;
    }

    public function planLogs(int $planId, array $params)
    {
        return MktAutoAppLogV2::select([
            'log_id', 'begin_time', 'end_time', 'created_time'
        ])
            ->where('plan_id', $planId)
            ->orderBy('created_time', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)));
    }

    public function planLogInfo(int $logId)
    {
        return MktAutoAppLogV2::findOrFail($logId);
    }

    protected function judgeScheduleTime(string $operate, string $scheduleTime): bool
    {
        // 巨量 2 [每半小时单位] 快手 1 [每1小时单位]
        $charNum = 2;
        $n = date('N');
        $scheduleTimeArr = str_split($scheduleTime, $charNum * 24);
        switch ($operate) {
            case 'empty-all':// 全部拉空
                $bool = $scheduleTime === str_repeat('0', $charNum * 24 * 7);
                break;
            case 'cancel-empty-all':// 取消全部拉空
                $bool = $scheduleTime === str_repeat('1', $charNum * 24 * 7);
                break;
            case 'empty-day':// 当日拉空
                $bool = $scheduleTimeArr[$n - 1] === str_repeat('0', $charNum * 24);
                break;
            case 'cancel-empty-day':// 取消当日拉空
                $bool = $scheduleTimeArr[$n - 1] === str_repeat('1', $charNum * 24);
                break;
            default:
                $bool = false;
        }

        return $bool;
    }

    protected function getScheduleTime(string $operate, string $scheduleTime): string
    {
        // 巨量 2 [每半小时单位] 快手 1 [每1小时单位]
        $charNum = 2;
        // 拉空
        if ($operate == 'empty-all') {
            return str_repeat('0', $charNum * 24 * 7);
        }
        // 当日星期
        $n = date('N');
        $newScheduleTime = '';
        switch ($operate) {
            case 'empty-day':
                for ($i = 1; $i <= 7; $i++) {
                    // 当日关闭
                    $newScheduleTime .= str_repeat($i == $n ? '0' : '1', $charNum * 24);
                }
                // 运算
                $scheduleTime = $scheduleTime & $newScheduleTime;
                break;
            case 'cancel-empty-day':
                for ($i = 1; $i <= 7; $i++) {
                    // 当日开启
                    $newScheduleTime .= str_repeat($i == $n ? '1' : '0', $charNum * 24);
                }
                // 运算
                $scheduleTime = $scheduleTime | $newScheduleTime;
                break;
        }

        return $scheduleTime;
    }

    protected function executePlan(array $plan)
    {
        go(function () use ($plan) {
            $threshold = $this->getThreshold($plan);
            if (empty($threshold)) {
                return;
            }
            $adInfoList = Db::table('report_daily_ad as rda')
                ->leftJoin('mkt_ad as ma', 'ma.ad_id', '=', 'rda.ad_id')
                ->leftJoin('report_daily_ad_ext as rdae', 'rdae.id', '=', 'rda.id')
                ->select([
                    'ma.put_status',
                    'rda.advertiser_id',
                    'rda.ad_id',
                    'rda.cost',
                    'rda.active_cost',
                    'rda.key_action_cost',
                    'rda.key_action_rate',
                    'rda.attribution_app_24h_roi',
                    'rdae.install_revenue as arpu',
                    DB::raw("IF(rda.cost > 0,round(rdae.install_revenue * 100/rda.cost,2),0) as roi")
                ])
                ->where('rda.platform', MktConst::PLATFORM_JL)// 暂时只处理巨量
                ->where('rda.app_code', data_get($plan, 'app_code'))
                ->where('rda.date', date('Y-m-d'))
                ->get()
                ->toArray();
            $batchAdInfo = [];
            foreach ($adInfoList as $adInfo) {
                $resultBool = $this->judgeAdvertiserThresholdReached($adInfo, $threshold);
                if ($resultBool) {
                    $batchAdInfo[data_get($adInfo, 'advertiser_id')][] = $adInfo;
                }
            }
            if (!empty($batchAdInfo)) {
                $this->schedulePlanExecute($plan, $batchAdInfo);
            }
        });
    }

    protected function adBatchData($mkt, $funcName, $plan, $advertiserId, $newAd, $times = 0)
    {
        try {
            $times++;
            return $mkt->$funcName(strval($advertiserId), $newAd);
        } catch (\Exception $exception) {
            if ($times < 3) {
                sleep(3);
                return $this->adBatchData($mkt, $funcName, $plan, $advertiserId, $newAd, $times);
            } else {
                $comment = $this->executeOperateCommentArr[data_get($plan, 'execute_operate')] ?? '';
                make(Lark::class)
                    ->setRobotCode('mkt_put')
                    ->pushText(json_encode([
                        'plan' => [
                            'app_code' => data_get($plan, 'app_code'),
                            'app_name' => data_get($plan, 'app_name'),
                            'plan_id' => data_get($plan, 'plan_id'),
                            'plan_name' => data_get($plan, 'plan_name'),
                        ],
                        'data' => [
                            'advertiser_id' => $advertiserId,
                            'ad_id' => array_column($newAd, 'ad_id')
                        ],
                        'comment' => $comment . ":执行多次仍然失败",
                        'message' => $exception->getMessage(),
                    ], 320));
                return [];
            }
        }
    }
    public function getThreshold($plan)
    {
        $hour = date("H", time());
        $minute = date('i', time());
        $threshold = data_get(data_get($plan, 'threshold'), $hour);
        if (!empty($threshold) && $minute < 30) {
            return $threshold;
        }

        $hour = $minute >= 30 ? "$hour:30" : "$hour:00";
        $threshold = data_get(data_get($plan, 'threshold'), $hour);
        return $threshold;
    }
}
