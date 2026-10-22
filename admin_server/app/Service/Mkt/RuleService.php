<?php

declare(strict_types=1);

namespace App\Service\Mkt;


use App\Constants\MktConst;
use App\Job\Mkt\MktJob;
use App\Model\Mkt\MktPlanRule;
use App\Model\Mkt\MktPlanRuleLog;
use App\Model\Mkt\ReportDailyPlan;
use App\Utils\Ding;

class RuleService
{
    /**
     * 当前日期
     * @var string
     */
    protected $nowDate;

    public function __construct(string $date = null)
    {
        $this->nowDate = $date ?? date('Y-m-d');
    }

    public function handle()
    {
        $list = $this->ruleList();
        foreach ($list as $ruleInfo) {
            $this->checkRule($ruleInfo);
        }
    }

    /**
     * 有效规则列表
     * @return array
     */
    protected function ruleList()
    {
        return MktPlanRule::query()
            ->select([
                'rule_id',
                'app_code',
                'advertiser_id',
                'plan_ids',
                'cycle_day',
                'cost',
                'threshold',
                'execute_type',
                'execute_value',
                'execute_result',
                'execute_times',
                'execute_date',
            ])
            ->where('end_date', '>=', $this->nowDate)
            ->where('start_date', '<=', $this->nowDate)
            ->where('deleted_time', 0)
            ->where('status', 1)
            ->get()
            ->toArray();
    }

    /**
     * 计划规则周期内是否已触发
     * @param int $ruleId
     * @param string $planId
     * @param int $times
     * @return bool
     */
    protected function checkPlanLog(int $ruleId, string $planId, int $times)
    {
        return MktPlanRuleLog::query()
            ->where('rule_id', $ruleId)
            ->where('plan_id', $planId)
            ->where('rule_times', $times)
            ->exists();
    }

    /**
     * 执行规则检查
     * @param array $ruleInfo
     */
    protected function checkRule(array $ruleInfo)
    {
        $list = ReportDailyPlan::query()->where('platform', '!=', MktConst::PLATFORM_TX)
            ->where('date', $this->nowDate)
            ->whereIn('plan_id', $ruleInfo['plan_ids'])
            ->get();

        foreach ($list as $p) {
            $planInfo = $p->toArray();
            // 联表处理
            $planInfo['plan'] = $p->plan->toArray();
            $planInfo['roi'] = round($planInfo['cost'] > 0 ? ($p->planExt->install_revenue ?? 0) / $planInfo['cost'] : 0, 4);
            $planInfo['arpu'] = $p->planExt->install_avg_revenue ?? 0;
            go(function () use ($ruleInfo, $planInfo) {
                $checkResult = $this->checkRulePlan($ruleInfo, $planInfo);

                if ($checkResult['result']) {
                    $this->executeRulePlan($ruleInfo, $planInfo, $checkResult);
                }
            });
        }
    }

    /**
     * 检查规则计划
     * @param array $ruleInfo
     * @param array $planInfo
     * @return array
     */
    protected function checkRulePlan(array $ruleInfo, array $planInfo)
    {
        $times = $this->getRuleTimes($ruleInfo['execute_date']);

        if ($times <= 0
            || $this->checkPlanLog($ruleInfo['rule_id'], $planInfo['plan_id'], $times)
            || !$this->checkCost($ruleInfo['cost'], $planInfo)) {
            return [
                'result' => false
            ];
        }

        $result = $this->checkThreshold($ruleInfo['threshold'], $planInfo);

        return [
            'result' => $result['result'],
            'threshold' => $result['threshold'],
            'times' => $times,
        ];
    }

    /**
     * 规则执行
     * @param array $ruleInfo
     * @param array $planInfo
     * @param array $checkResult
     */
    protected function executeRulePlan(array $ruleInfo, array $planInfo, array $checkResult)
    {
        $executeArr = [
            'remind' => 'ruleRemind',
            'execute' => 'ruleExecute',
        ];

        foreach ($ruleInfo['execute_result'] ?? [] as $v) {
            if (isset($executeArr[$v])) {
                call_user_func_array([$this, $executeArr[$v]], [$ruleInfo, $planInfo, $checkResult]);
            }
        }

        (new MktPlanRuleLog())
            ->fill([
                'rule_id' => $ruleInfo['rule_id'],
                'plan_id' => $planInfo['plan_id'],
                'rule_times' => $checkResult['times'],
                'hit_threshold' => $checkResult['threshold'],
                'rule_data' => $ruleInfo
            ])->save();
    }

    /**
     * 规则提醒
     * @param array $ruleInfo
     * @param array $planInfo
     * @param array $checkResult
     * @throws \Exception
     */
    protected function ruleRemind(array $ruleInfo, array $planInfo, array $checkResult)
    {
        $threshold = $checkResult['threshold'];
        $content = sprintf("【计划】:%s-%s 【达成规则】:%s ", $planInfo['plan_id'], $planInfo['plan']['plan_name'], MktConst::RULE_THRESHOLD_TYPE[$threshold['k']] . MktConst::RULE_OPERATE[$threshold['v']['operator']] . $threshold['v']['value']);
        make(Ding::class)
            ->setRobotCode(MktConst::ROBOT_CODE)
            ->pushText($content);
    }

    /**
     * 规则执行
     * @param $ruleInfo
     * @param $planInfo
     */
    protected function ruleExecute($ruleInfo, $planInfo)
    {
        $update = [
            'status' => 'planUpdateStatus',
            'budget' => 'planUpdateBudget',
            'empty' => 'planEmptyDayScheduleTime',
        ];

        if (in_array($ruleInfo['execute_type'], ['open', 'close'])) {
            queue_push(new MktJob($update['status'], [
                $planInfo['plan_id'],
                $ruleInfo['execute_type'] == 'open' ? 'enable' : 'disable'
            ]), MktConst::QUEUE_KEY);
        }

        switch ($ruleInfo['execute_type']) {
            case 'open':
            case 'close':
                queue_push(new MktJob($update['status'], [
                    $planInfo['plan_id'],
                    $ruleInfo['execute_type'] == 'open' ? 'enable' : 'disable'
                ]), MktConst::QUEUE_KEY);
                break;
            case 'budget':
                $operator = $ruleInfo['execute_value']['budget']['operator'] ?? '';
                $value = $ruleInfo['execute_value']['budget']['value'] ?? 0;

                if (in_array($operator, ['up', 'down']) && $value > 0) {
                    $amount = round($planInfo['plan']['budget_amount'] * $value / 100, 2);
                    $budgetAmount = $planInfo['plan']['budget_amount'] + ($operator == 'up' ? $amount : -$amount);
                    // 不得低于当日消耗金额的120% && 最低100
                    $budgetAmount = max($budgetAmount, $planInfo['plan']['cost'] * 1.2, 100);
                    // 预算整百单位向上取整
                    $budgetAmount = ceil($budgetAmount / 100) * 100;

                    queue_push(new MktJob($update['budget'], [
                        $planInfo['plan_id'],
                        $budgetAmount
                    ]), MktConst::QUEUE_KEY);
                }
                break;
            case 'empty':
                queue_push(new MktJob($update['empty'], [
                    $planInfo['plan_id']
                ]), MktConst::QUEUE_KEY);
                break;
        }
    }

    /**
     * 获取当前所处规则周期
     * @param array $ruleExecuteDate
     * @return int
     */
    protected function getRuleTimes(array $ruleExecuteDate)
    {
        $timestamp = strtotime($this->nowDate);

        foreach ($ruleExecuteDate as $times => $date) {
            if ($timestamp >= strtotime($date['start_date']) && $timestamp <= strtotime($date['end_date'])) {
                return (int)$times;
            }
        }

        return 0;
    }

    /**
     * 检查消耗
     * @param array $ruleCost
     * @param array $planInfo
     * @return bool
     */
    protected function checkCost(array $ruleCost, array $planInfo)
    {
        return $this->valueOperator(floatval($planInfo['cost'] ?? 0), floatval($ruleCost['value'] ?? 0), $ruleCost['operator'] ?? '');
    }

    /**
     * 检查阈值指标
     * @param array $ruleThreshold
     * @param array $planInfo
     * @return array
     */
    protected function checkThreshold(array $ruleThreshold, array $planInfo)
    {
        foreach (MktConst::RULE_THRESHOLD_TYPE_KEY as $key => $value) {
            $value1 = floatval($planInfo[$value] ?? 0);
            $value2 = floatval($ruleThreshold[$key]['value'] ?? 0);
            $operator = strval($ruleThreshold[$key]['operator'] ?? '');

            if ($this->valueOperator($value1, $value2, $operator)) {
                $result = [
                    'result' => true,
                    'threshold' => [
                        'k' => $key,
                        'v' => $ruleThreshold[$key] ?? []
                    ],
                ];
                break;
            }
        }

        return $result ?? [
                'result' => false,
                'threshold' => [],
            ];
    }

    /**
     * 操作运算
     * @param float $value1
     * @param float $value2
     * @param string $operator
     * @return bool
     */
    protected function valueOperator(float $value1, float $value2, string $operator)
    {
        if ($value1 <= 0 || $value2 <= 0) {
            return false;
        }
        switch ($operator) {
            case 'lt':
                $result = $value1 < $value2;
                break;
            case 'le':
                $result = $value1 <= $value2;
                break;
            case 'eq':
                $result = $value1 == $value2;
                break;
            case 'gt':
                $result = $value1 > $value2;
                break;
            case 'ge':
                $result = $value1 >= $value2;
                break;
            default:
                $result = false;
        }

        return $result;
    }
}