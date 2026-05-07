<?php

declare(strict_types=1);

namespace App\Service\Mkt;

use App\Model\Mkt\MktAlertRule;
use App\Model\Mkt\MktAlertDelegate;
use App\Model\Mkt\MktAlertRuleDelegate;
use Hyperf\DbConnection\Db;

class AlertRuleService
{
    /**
     * 创建规则
     */
    public function createRule(array $data): MktAlertRule
    {
        Db::beginTransaction();
        try {
            $delegateIds = $data['delegate_ids'] ?? [];
            unset($data['delegate_ids']);

            $rule = new MktAlertRule();
            $rule->fill($data)->save();

            // 关联代投配置
            if (!empty($delegateIds)) {
                foreach ($delegateIds as $delegateId) {
                    MktAlertRuleDelegate::create([
                        'rule_id' => $rule->rule_id,
                        'delegate_id' => $delegateId
                    ]);
                }
            }

            Db::commit();
            return $rule;
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 规则列表
     */
    public function ruleList(array $params)
    {
        $query = MktAlertRule::query()
            ->where('deleted_time', 0)
            ->orderBy('created_time', 'desc');

        // 过滤条件
        if (!empty($params['rule_type'])) {
            $query->where('rule_type', $params['rule_type']);
        }
        if (isset($params['rule_enable'])) {
            $query->where('rule_enable', $params['rule_enable']);
        }
        if (!empty($params['rule_name'])) {
            $query->where('rule_name', 'like', '%' . $params['rule_name'] . '%');
        }
        if (!empty($params['rule_id'])) {
            $query->where('rule_id', $params['rule_id']);
        }
        if (!empty($params['app_code'])) {
            $appCodes = is_array($params['app_code']) ? $params['app_code'] : [$params['app_code']];
            $query->where(function($q) use ($appCodes) {
                foreach ($appCodes as $code) {
                    $q->orWhereRaw("JSON_CONTAINS(app_code, '\"" . $code . "\"')");
                }
            });
        }

        return $query->paginate((int)($params['per_page'] ?? 10));
    }

    /**
     * 规则详情
     */
    public function ruleInfo(int $ruleId): ?MktAlertRule
    {
        $rule = MktAlertRule::query()
            ->where('rule_id', $ruleId)
            ->where('deleted_time', 0)
            ->first();

        if ($rule) {
            $delegateIds = MktAlertRuleDelegate::query()
                ->where('rule_id', $ruleId)
                ->pluck('delegate_id')
                ->toArray();
            $rule->delegate_ids = $delegateIds;
        }

        return $rule;
    }

    /**
     * 更新规则
     */
    public function updateRule(int $ruleId, array $data): bool
    {
        Db::beginTransaction();
        try {
            $delegateIds = $data['delegate_ids'] ?? [];
            unset($data['delegate_ids']);

            $rule = MktAlertRule::query()
                ->where('rule_id', $ruleId)
                ->where('deleted_time', 0)
                ->first();

            if (!$rule) {
                throw new \Exception('规则不存在');
            }

            $rule->fill($data)->save();

            // 更新代投关联
            MktAlertRuleDelegate::query()->where('rule_id', $ruleId)->delete();
            if (!empty($delegateIds)) {
                foreach ($delegateIds as $delegateId) {
                    MktAlertRuleDelegate::create([
                        'rule_id' => $ruleId,
                        'delegate_id' => $delegateId
                    ]);
                }
            }

            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 启用/关闭规则
     */
    public function enableRule(int $ruleId, int $enable): bool
    {
        $rule = MktAlertRule::query()
            ->where('rule_id', $ruleId)
            ->where('deleted_time', 0)
            ->first();

        if (!$rule) {
            throw new \Exception('规则不存在');
        }

        $rule->rule_enable = $enable;
        return $rule->save();
    }

    /**
     * 删除规则
     */
    public function deleteRule(int $ruleId): bool
    {
        $rule = MktAlertRule::query()
            ->where('rule_id', $ruleId)
            ->where('deleted_time', 0)
            ->first();

        if (!$rule) {
            throw new \Exception('规则不存在');
        }

        $rule->deleted_time = time();
        return $rule->save();
    }
}
