<?php

declare(strict_types=1);

namespace App\Service\Mkt;

use App\Model\Mkt\MktAlertRule;
use App\Model\Mkt\MktAlertDelegate;
use App\Model\Mkt\MktAlertRuleDelegate;
use App\Model\Mkt\MktAlertLog;
use App\Model\Mkt\MktAdvertiser;
use App\Model\Mkt\MktProject;
use App\Model\Mkt\ReportDailyAdvertiser;
use App\Model\Mkt\ReportDailyAdvertiserExt;
use App\Model\Mkt\ReportDailyProject;
use App\Model\ReportDailyProjectExt;
use App\Utils\Log;
use App\Utils\Ding;
use Hyperf\DbConnection\Db;

class AlertCheckService
{
    /**
     * 当前时间 H:i 格式
     * @var string
     */
    protected $currentTime;

    public function __construct()
    {
        $this->currentTime = date('H:i');
    }

    /**
     * 检查所有启用的规则
     */
    public function checkAllRules(): void
    {
        $rules = MktAlertRule::query()
            ->where('rule_enable', 1)
            ->where('deleted_time', 0)
            ->get();

        foreach ($rules as $rule) {
            try {
                // 获取当前时间点对应的阈值
                $threshold = $this->getTimePointThreshold($rule);
                if ($threshold === null) {
                    // 当前时间不在配置的时间点中，跳过
                    continue;
                }

                if ($rule->rule_type === 'advertiser') {
                    $this->checkAdvertiserRule($rule, $threshold);
                } else {
                    $this->checkProjectRule($rule, $threshold);
                }
            } catch (\Throwable $e) {
                Log::system('error', '检查规则失败', [
                    'rule_id' => $rule->rule_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 检查完成后，更新报警状态
        $this->updateAlertStatus();
    }

    /**
     * 检查所有启用的广告主规则
     */
    public function checkAdvertiserRules(): void
    {
        $rules = MktAlertRule::query()
            ->where('rule_enable', 1)
            ->where('rule_type', 'advertiser')
            ->where('deleted_time', 0)
            ->get();

        foreach ($rules as $rule) {
            try {
                $threshold = $this->getTimePointThreshold($rule);
                if ($threshold === null) {
                    continue;
                }
                $this->checkAdvertiserRule($rule, $threshold);
            } catch (\Throwable $e) {
                Log::system('error', '检查广告主规则失败', [
                    'rule_id' => $rule->rule_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 检查完成后，更新报警状态
        $this->updateAdvertiserAlertStatus();
    }

    /**
     * 检查所有启用的项目规则
     */
    public function checkProjectRules(): void
    {
        $rules = MktAlertRule::query()
            ->where('rule_enable', 1)
            ->where('rule_type', 'project')
            ->where('deleted_time', 0)
            ->get();

        foreach ($rules as $rule) {
            try {
                $threshold = $this->getTimePointThreshold($rule);
                if ($threshold === null) {
                    continue;
                }
                $this->checkProjectRule($rule, $threshold);
            } catch (\Throwable $e) {
                Log::system('error', '检查项目规则失败', [
                    'rule_id' => $rule->rule_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 检查完成后，更新报警状态
        $this->updateProjectAlertStatus();
    }

    /**
     * 获取当前时间点对应的阈值配置
     * @param MktAlertRule $rule
     * @return array|null 返回当前时间点对应的阈值，如果当前时间不在配置的时间点中则返回null
     */
    protected function getTimePointThreshold(MktAlertRule $rule): ?array
    {
        $timePoints = $rule->time_points ?? [];

        // 如果配置了时间点，则匹配当前时间
        if (!empty($timePoints)) {
            // 先尝试精确匹配当前时间
            foreach ($timePoints as $point) {
                if (isset($point['time']) && $point['time'] === $this->currentTime) {
                    return $point['threshold'] ?? [];
                }
            }

            // 如果没有精确匹配，按照半小时间隔匹配
            $hour = date('H');
            $minute = (int)date('i');
            $targetTime = $minute >= 30 ? sprintf('%02d:30', (int)$hour) : sprintf('%02d:00', (int)$hour);

            foreach ($timePoints as $point) {
                if (isset($point['time']) && $point['time'] === $targetTime) {
                    return $point['threshold'] ?? [];
                }
            }

            // 当前时间不在配置的时间点中
            return null;
        }

        // 如果没有配置时间点，使用默认的 threshold（兼容旧版）
        return $rule->threshold ?? [];
    }

    /**
     * 检查广告主规则
     */
    protected function checkAdvertiserRule(MktAlertRule $rule, array $threshold): void
    {
        $today = date('Y-m-d');

        // 获取规则关联的代投配置
        $delegates = $this->getRuleDelegates($rule->rule_id);
        if (empty($delegates)) {
            return;
        }

        // 查询广告主数据
        $query = MktAdvertiser::query()
            ->where('status', 1)
            ->whereExists(function ($query) use ($today) {
                $query->select(Db::raw(1))
                    ->from('report_daily_advertiser')
                    ->whereRaw("report_daily_advertiser.advertiser_id = mkt_advertiser.advertiser_id AND report_daily_advertiser.date = '{$today}'");
            });

        // 应用过滤
        if (!empty($rule->app_code)) {
            $query->whereIn('app_code', $rule->app_code);
        }

        $advertisers = $query->get();

        foreach ($advertisers as $advertiser) {
            try {
                $this->checkAdvertiserItem($rule, $advertiser, $delegates, $today, $threshold);
            } catch (\Throwable $e) {
                var_dump($e);
            }
        }
    }

    /**
     * 检查项目规则
     */
    protected function checkProjectRule(MktAlertRule $rule, array $threshold): void
    {
        $today = date('Y-m-d');

        // 获取规则关联的代投配置
        $delegates = $this->getRuleDelegates($rule->rule_id);
        if (empty($delegates)) {
            return;
        }

        // 查询项目数据
        $query = MktProject::query()
            ->where('deleted_time', 0)
            ->whereExists(function ($query) use ($today) {
                $query->select(Db::raw(1))
                    ->from('report_daily_project')
                    ->whereRaw("report_daily_project.project_id = mkt_project.project_id AND report_daily_project.date = '{$today}'");
            });

        // 应用过滤
        if (!empty($rule->app_code)) {
            $query->whereIn('app_code', $rule->app_code);
        }

        $projects = $query->get();

        foreach ($projects as $project) {
            $this->checkProjectItem($rule, $project, $delegates, $today, $threshold);
        }
    }

    /**
     * 检查单个广告主
     */
    protected function checkAdvertiserItem(MktAlertRule $rule, MktAdvertiser $advertiser, array $delegates, string $date, array $threshold): void
    {
        $report = ReportDailyAdvertiser::query()
            ->where('advertiser_id', $advertiser->advertiser_id)
            ->where('date', $date)
            ->first();

        if (!$report) {
            return;
        }

        // 获取扩展表数据
        $reportExt = ReportDailyAdvertiserExt::query()
            ->where('id', $report->id)
            ->first();

        // 计算消耗占比（从日报表获取日预算）
        $budget = (float)($report->budget ?? 0);
        $costPercent = $budget > 0 ? ($report->cost / $budget) : 0;

        // 计算ROI：install_revenue24/cost
        $cost = (float)($report->cost ?? 0);
        $installRevenue24 = $reportExt ? (float)($reportExt->install_revenue24 ?? 0) : 0;
        $roi = $cost > 0 ? ($installRevenue24 / $cost) : 0;

        // 巨量ROI：attribution_app_24h_roi
        $attributionRoi = (float)($report->attribution_app_24h_roi ?? 0);

        $triggered = true;
        $triggerData = [];
        $triggerData['cost'] = $cost;

        // 消耗百分比
        if (isset($threshold['cost_percent'])) {
            $operator = $threshold['cost_percent']['operator'];
            $value = $threshold['cost_percent']['value'];
            $triggerData['cost_percent'] = $costPercent;

            if (!$this->compare($costPercent, $operator, $value)) {
                $triggered = false;
            }
        }

        // ROI (自有后端数据)
        if (isset($threshold['roi'])) {
            $operator = $threshold['roi']['operator'];
            $value = $threshold['roi']['value'];
            $triggerData['roi'] = $roi;

            if (!$this->compare($roi, $operator, $value)) {
                $triggered = false;
            }
        }

        // 巨量ROI (attribution_app_24h_roi)
        if (isset($threshold['attribution_app_24h_roi'])) {
            $operator = $threshold['attribution_app_24h_roi']['operator'];
            $value = $threshold['attribution_app_24h_roi']['value'];
            $triggerData['attribution_app_24h_roi'] = $attributionRoi;

            if (!$this->compare($attributionRoi, $operator, $value)) {
                $triggered = false;
            }
        }

        // 检查状态变化
        $this->handleAlertLog($rule, 'advertiser', $advertiser->advertiser_id, $advertiser->advertiser_name, $triggered, $triggerData, $delegates);
    }

    /**
     * 检查单个项目
     */
    protected function checkProjectItem(MktAlertRule $rule, MktProject $project, array $delegates, string $date, array $threshold): void
    {
        $report = ReportDailyProject::query()
            ->where('project_id', $project->project_id)
            ->where('date', $date)
            ->first();

        if (!$report) {
            return;
        }

        // 获取扩展表数据
        $reportExt = ReportDailyProjectExt::query()
            ->where('id', $report->id)
            ->first();

        // 计算消耗占比
        $budget = (float)($project->budget_amount ?? 0);
        $costPercent = $budget > 0 ? ($report->cost / $budget) : 0;

        // 计算ROI：install_revenue24/cost
        $cost = (float)($report->cost ?? 0);
        $installRevenue24 = $reportExt ? (float)($reportExt->install_revenue24 ?? 0) : 0;
        $roi = $cost > 0 ? ($installRevenue24 / $cost) : 0;

        // 巨量ROI：attribution_app_24h_roi
        $attributionRoi = (float)($report->attribution_app_24h_roi ?? 0);

        $triggered = true;
        $triggerData = [];
        $triggerData['cost'] = $cost;

        // 消耗百分比
        if (isset($threshold['cost_percent'])) {
            $operator = $threshold['cost_percent']['operator'];
            $value = $threshold['cost_percent']['value'];
            $triggerData['cost_percent'] = $costPercent;

            if (!$this->compare($costPercent, $operator, $value)) {
                $triggered = false;
            }
        }

        // ROI (自有后端数据)
        if (isset($threshold['roi'])) {
            $operator = $threshold['roi']['operator'];
            $value = $threshold['roi']['value'];
            $triggerData['roi'] = $roi;

            if (!$this->compare($roi, $operator, $value)) {
                $triggered = false;
            }
        }

        // 巨量ROI (attribution_app_24h_roi)
        if (isset($threshold['attribution_app_24h_roi'])) {
            $operator = $threshold['attribution_app_24h_roi']['operator'];
            $value = $threshold['attribution_app_24h_roi']['value'];
            $triggerData['attribution_app_24h_roi'] = $attributionRoi;

            if (!$this->compare($attributionRoi, $operator, $value)) {
                $triggered = false;
            }
        }

        // 获取广告主信息
        $advertiser = MktAdvertiser::query()
            ->where('advertiser_id', $project->advertiser_id)
            ->first();

        $advertiserName = $advertiser ? $advertiser->advertiser_name : '';

        // 检查状态变化
        $this->handleAlertLog($rule, 'project', $project->project_id, $project->project_name, $triggered, $triggerData, $delegates, $advertiserName);
    }

    /**
     * 处理报警日志和通知
     */
    protected function handleAlertLog(MktAlertRule $rule, string $itemType, string $itemId, string $itemName, bool $triggered, array $triggerData, array $delegates, string $advertiserName = ''): void
    {
        // 查询现有日志
        $log = MktAlertLog::query()
            ->where('rule_id', $rule->rule_id)
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->first();

        $oldStatus = $log ? $log->status : 0;
        $newStatus = $triggered ? 1 : 0;

        // 状态从 0 -> 1 时发送通知
        if ($oldStatus === 0 && $newStatus === 1) {
            $this->sendNotification($rule, $itemType, $itemId, $itemName, $triggerData, $delegates, $advertiserName);
        }

        // 更新日志
        if ($log) {
            $log->status = $newStatus;
            $log->trigger_data = $triggerData;
            if ($newStatus === 1 && $oldStatus === 0) {
                $log->notified_time = time();
            }
            $log->save();
        } else {
            MktAlertLog::create([
                'rule_id' => $rule->rule_id,
                'item_type' => $itemType,
                'item_id' => $itemId,
                'item_name' => $itemName,
                'trigger_data' => $triggerData,
                'status' => $newStatus,
                'notified_time' => $newStatus === 1 ? time() : null
            ]);
        }
    }

    /**
     * 发送通知
     */
    protected function sendNotification(MktAlertRule $rule, string $itemType, string $itemId, string $itemName, array $triggerData, array $delegates, string $advertiserName = ''): void
    {
        foreach ($delegates as $delegate) {
            // 模糊匹配广告主名称
            if (strpos($advertiserName ?: $itemName, $delegate['delegate_name']) !== false) {
                $ding = new Ding();
                $ding->setDingConfig($delegate['ding_token'], $delegate['ding_secret']);

                $message = $this->buildMessage($rule, $itemType, $itemId, $itemName, $triggerData);
                $ding->pushMarkdown('消耗预警提醒', $message);
            }
        }
    }

    /**
     * 构建消息
     */
    protected function buildMessage(MktAlertRule $rule, string $itemType, string $itemId, string $itemName, array $triggerData): string
    {
        $lines = [];
        $lines[] = "### 🚨 消耗预警提醒\n";
        $lines[] = "**规则名称**：{$rule->rule_name}";
        $lines[] = "**触发时间**：" . date('Y-m-d H:i:s');
        $lines[] = "\n---\n";

        $cost_percent = number_format($triggerData['cost_percent'] ?? 0, 2);
        $cost = number_format($triggerData['cost'] ?? 0, 2);
        $jl_roi = number_format($triggerData['attribution_app_24h_roi'] ?? 0, 2);

        if ($itemType === 'project') {
            $lines[] = "以下项目触发报警规则：\n";
            $lines[] = "| 项目ID | 项目名称 | 消耗占比 | 消耗 | 巨量ROI |";
            $lines[] = "|--------|----------|----------|------|---------|";
            $lines[] = "| {$itemId} | {$itemName} | **" . $cost_percent . "** | **" . $cost . "** | **" . $jl_roi . "** |";
        } else {
            $lines[] = "以下广告主触发报警规则：\n";
            $lines[] = "| 广告主ID | 广告主名称 | 消耗占比 | 消耗 | 巨量ROI |";
            $lines[] = "|----------|------------|----------|------|---------|";
            $lines[] = "| {$itemId} | {$itemName} | **" . $cost_percent . "** | **" . $cost . "** | **" . $jl_roi . "** |";
        }

        $lines[] = "\n---";
        $lines[] = "⏰ " . date('Y-m-d H:i:s');

        return implode("\n", $lines);
    }

    /**
     * 比较函数
     */
    protected function compare($actual, string $operator, $expected): bool
    {
        switch ($operator) {
            case 'gt':
                return $actual > $expected;
            case 'ge':
                return $actual >= $expected;
            case 'lt':
                return $actual < $expected;
            case 'le':
                return $actual <= $expected;
            case 'eq':
                return $actual == $expected;
            default:
                return false;
        }
    }

    /**
     * 获取规则关联的代投配置
     */
    protected function getRuleDelegates(int $ruleId): array
    {
        $delegateIds = MktAlertRuleDelegate::query()
            ->where('rule_id', $ruleId)
            ->pluck('delegate_id')
            ->toArray();

        if (empty($delegateIds)) {
            return [];
        }

        return MktAlertDelegate::query()
            ->whereIn('delegate_id', $delegateIds)
            ->where('status', 1)
            ->where('deleted_time', 0)
            ->get()
            ->toArray();
    }

    /**
     * 更新广告主/项目的报警状态
     * 在每次检查完所有规则后调用
     */
    public function updateAlertStatus(): void
    {
        $this->updateAdvertiserAlertStatus();
        $this->updateProjectAlertStatus();
    }

    /**
     * 更新所有广告主的报警状态
     */
    protected function updateAdvertiserAlertStatus(): void
    {
        // 1. 先将所有广告主状态设为"未知"(0)
        MktAdvertiser::query()->update(['alert_status' => 0]);

        // 2. 获取所有启用的广告主报警规则
        $rules = MktAlertRule::query()
            ->where('rule_enable', 1)
            ->where('rule_type', 'advertiser')
            ->where('deleted_time', 0)
            ->get();

        if ($rules->isEmpty()) {
            return; // 没有启用的规则，保持全部为未知
        }

        // 3. 收集需要检查的应用代码
        $hasGlobalRule = false; // 是否有适用于所有应用的规则
        $enabledAppCodes = [];

        foreach ($rules as $rule) {
            $appCodes = $rule->app_code ?? [];
            if (empty($appCodes)) {
                $hasGlobalRule = true;
                break; // 有全局规则，不需要继续收集
            }
            $enabledAppCodes = array_merge($enabledAppCodes, $appCodes);
        }

        // 4. 将有适用规则的所有广告主设为"正常"(1)
        if ($hasGlobalRule) {
            // 有全局规则，所有广告主都设为正常
            MktAdvertiser::query()->update(['alert_status' => 1]);
        } else {
            $enabledAppCodes = array_unique($enabledAppCodes);
            if (!empty($enabledAppCodes)) {
                MktAdvertiser::query()
                    ->whereIn('app_code', $enabledAppCodes)
                    ->update(['alert_status' => 1]);
            }
        }

        // 5. 将有触发记录的广告主设为"报警"(2)
        $triggeredAdvertiserIds = MktAlertLog::query()
            ->where('item_type', 'advertiser')
            ->where('status', 1)
            ->pluck('item_id')
            ->unique()
            ->toArray();

        if (!empty($triggeredAdvertiserIds)) {
            MktAdvertiser::query()
                ->whereIn('advertiser_id', $triggeredAdvertiserIds)
                ->update(['alert_status' => 2]);
        }
    }

    /**
     * 更新所有项目的报警状态
     */
    protected function updateProjectAlertStatus(): void
    {
        // 1. 先将所有项目状态设为"未知"(0)
        MktProject::query()->update(['alert_status' => 0]);

        // 2. 获取所有启用的项目报警规则
        $rules = MktAlertRule::query()
            ->where('rule_enable', 1)
            ->where('rule_type', 'project')
            ->where('deleted_time', 0)
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        // 3. 收集需要检查的应用代码
        $hasGlobalRule = false; // 是否有适用于所有应用的规则
        $enabledAppCodes = [];

        foreach ($rules as $rule) {
            $appCodes = $rule->app_code ?? [];
            if (empty($appCodes)) {
                $hasGlobalRule = true;
                break; // 有全局规则，不需要继续收集
            }
            $enabledAppCodes = array_merge($enabledAppCodes, $appCodes);
        }

        // 4. 将有适用规则的所有项目设为"正常"(1)
        if ($hasGlobalRule) {
            // 有全局规则，所有项目都设为正常
            MktProject::query()->update(['alert_status' => 1]);
        } else {
            $enabledAppCodes = array_unique($enabledAppCodes);
            if (!empty($enabledAppCodes)) {
                MktProject::query()
                    ->whereIn('app_code', $enabledAppCodes)
                    ->update(['alert_status' => 1]);
            }
        }

        // 5. 将有触发记录的项目设为"报警"(2)
        $triggeredProjectIds = MktAlertLog::query()
            ->where('item_type', 'project')
            ->where('status', 1)
            ->pluck('item_id')
            ->unique()
            ->toArray();

        if (!empty($triggeredProjectIds)) {
            MktProject::query()
                ->whereIn('project_id', $triggeredProjectIds)
                ->update(['alert_status' => 2]);
        }
    }

}
