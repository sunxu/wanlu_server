<?php

declare(strict_types=1);

namespace App\Controller\Put;

use App\Controller\AbstractController;
use App\Service\Mkt\AutoPlanService;
use Hyperf\Di\Annotation\Inject;

class AutoPlanController extends AbstractController
{
    /**
     * @Inject
     * @var AutoPlanService
     */
    protected $autoPlanService;

    public function savePlan(string $appCode)
    {
        $this->autoPlanService->savePlan($appCode, $this->request->all());

        return $this->responseSuccess();
    }

    public function checkPlanConflict(string $appCode)
    {
        $params = $this->request->all();
        $advertiserIds = data_get($params, 'advertiser_ids', []);
        $thresholdKeys = data_get($params, 'threshold_keys');
        $planId = data_get($params, 'plan_id', 0);
        $conflictList = $this->autoPlanService->checkPlanConflict($appCode, $planId, $advertiserIds, $thresholdKeys);

        return $this->responseRecord($conflictList);
    }

    public function planList(string $appCode)
    {
        $list = $this->autoPlanService->planList($appCode, $this->request->all());

        return $this->responsePaginator($list->items(), $list);
    }

    public function planInfo(string $appCode, int $planId)
    {
        $info = $this->autoPlanService->planInfo($planId);

        return $this->responseSuccess($info);
    }

    public function editPlan(string $appCode, int $planId)
    {
        $this->autoPlanService->editPlan($appCode, $planId, $this->request->all());

        return $this->responseSuccess();
    }

    public function enablePlan(string $appCode, int $planId)
    {
        $this->autoPlanService->enablePlan($planId);

        return $this->responseSuccess();
    }

    public function planLogs(string $appCode, int $planId)
    {
        $logList = $this->autoPlanService->planLogs($planId, $this->request->all());

        return $this->responsePaginator($logList->items(), $logList);
    }

    public function planLogInfo(string $appCode, int $planId, int $logId)
    {
        $logInfo = $this->autoPlanService->planLogInfo($logId);

        return $this->responseSuccess($logInfo);
    }
}