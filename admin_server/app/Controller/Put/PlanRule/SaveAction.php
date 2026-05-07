<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Controller\Put\PlanRule;

use App\Controller\AbstractAction;
use App\Model\Mkt\MktPlanRule;
use App\Utils\ValidateTrait;
use Hyperf\DbConnection\Db;

class SaveAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'rule_id' => ['nullable'],
            'confirm' => ['nullable'],
            'advertiser_id' => ['required'],
            'plan_ids' => ['required'],
            'app_code' => ['required'],//app_code
            'name' => ['required'],//规则名称
            'cost' => ['required'],//消耗
            'cycle_day' => ['required'],//周期
            'threshold' => ['required'],//阀值
            'execute_type' => ['required'],//执行
            'execute_value' => ['required'],//执行设置
            'execute_result' => ['required'],//执行回执
            'execute_times' => ['required'],//执行频率
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());

        $confirm = $params['confirm'] ?? 0;
        $list = MktPlanRule::query()
            ->where('advertiser_id', $params['advertiser_id'])
            ->where('deleted_time', 0)
            ->where('rule_id', '!=', $params['rule_id'])
            ->select([
                'rule_id',
                'name',
                'plan_ids'
            ])
            ->get()
            ->toArray();
        $rule = [];
        foreach ($list as $v) {
            $v['plan_ids'] = array_values(array_intersect($params['plan_ids'], $v['plan_ids']));
            if (!empty($v['plan_ids'])) {
                $rule[] = $v;
            }
        }

        if (!$confirm && !empty($rule)) {
            return $this->responseSuccess($rule);
        }

        Db::beginTransaction();
        try {
            foreach ($rule as $v) {
                $m = MktPlanRule::find($v['rule_id']);
                $m->fill([
                    'plan_ids' => array_values(array_diff($m->plan_ids, $v['plan_ids']))
                ])->save();

            }

            if ($params['rule_id']) {
                $model = MktPlanRule::find($params['rule_id']);
            } else {
                $model = new MktPlanRule();
            }

            $executeDate = [];
            $timestamp = $model->start_date ? strtotime($model->start_date) : time();

            for ($i = 1; $i <= $params['execute_times']; $i++) {
                $startDay = max($params['cycle_day'] * ($i - 1), 0);
                $endDay = max($params['cycle_day'] * $i - 1, 0);
                $executeDate[$i] = [
                    'start_date' => date('Y-m-d', strtotime("+ {$startDay} day", $timestamp)),
                    'end_date' => date('Y-m-d', strtotime("+ {$endDay} day", $timestamp)),
                ];
            }

            $params['execute_date'] = $executeDate;
            $params['start_date'] = $executeDate[1]['start_date'];
            $params['end_date'] = $executeDate[$params['execute_times']]['start_date'];
            $model->fill($params)->save();

            Db::commit();

            return $this->responseSuccess();
        } catch (\Throwable $e) {
            Db::rollBack();
            return $this->responseError(-1, $e->getMessage());
        }
    }
}