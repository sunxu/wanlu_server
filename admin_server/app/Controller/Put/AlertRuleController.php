<?php

declare(strict_types=1);

namespace App\Controller\Put;

use App\Controller\AbstractController;
use App\Service\Mkt\AlertRuleService;
use Hyperf\Di\Annotation\Inject;

class AlertRuleController extends AbstractController
{
    /**
     * @Inject
     * @var AlertRuleService
     */
    protected $alertRuleService;

    public function __construct()
    {
        $this->alertRuleService = make(AlertRuleService::class);
    }


    /**
     * 创建规则
     */
    public function saveRule()
    {
        $rule = $this->alertRuleService->createRule($this->request->all());
        return $this->responseSuccess($rule);
    }

    /**
     * 规则列表
     */
    public function ruleList()
    {
        $list = $this->alertRuleService->ruleList($this->request->all());
        return $this->responsePaginator($list->items(), $list);
    }

    /**
     * 规则详情
     */
    public function ruleInfo(int $ruleId)
    {
        $info = $this->alertRuleService->ruleInfo($ruleId);
        if (!$info) {
            return $this->responseError(-1, '规则不存在');
        }
        return $this->responseSuccess($info);
    }

    /**
     * 更新规则
     */
    public function updateRule(int $ruleId)
    {
        $this->alertRuleService->updateRule($ruleId, $this->request->all());
        return $this->responseSuccess();
    }

    /**
     * 启用/关闭规则
     */
    public function enableRule(int $ruleId)
    {
        $enable = $this->request->input('enable', 1);
        $this->alertRuleService->enableRule($ruleId, (int)$enable);
        return $this->responseSuccess();
    }

    /**
     * 删除规则
     */
    public function deleteRule(int $ruleId)
    {
        $this->alertRuleService->deleteRule($ruleId);
        return $this->responseSuccess();
    }
}
