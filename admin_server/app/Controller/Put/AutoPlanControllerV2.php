<?php

declare(strict_types=1);

namespace App\Controller\Put;

use App\Controller\AbstractController;
use App\Service\Mkt\AutoPlanServiceV2;
use Hyperf\Di\Annotation\Inject;

class AutoPlanControllerV2 extends AbstractController
{
    /**
     * @Inject
     * @var AutoPlanServiceV2
     */
    protected $autoPlanServiceV2;

    public function savePlan(string $appCode)
    {
        $this->autoPlanServiceV2->savePlan($appCode, $this->request->all());

        return $this->responseSuccess();
    }

    public function checkPlanConflict(string $appCode)
    {
        $params = $this->request->all();
        $advertiserIds = data_get($params, 'advertiser_ids', []);
        $thresholdKeys = data_get($params, 'threshold_keys');
        $planId = data_get($params, 'plan_id', 0);
        $conflictList = $this->autoPlanServiceV2->checkPlanConflict($appCode, $planId, $advertiserIds, $thresholdKeys);

        return $this->responseRecord($conflictList);
    }

    public function planList(string $appCode)
    {
        $list = $this->autoPlanServiceV2->planList($appCode, $this->request->all());

        return $this->responsePaginator($list->items(), $list);
    }

    public function planInfo(string $appCode, int $planId)
    {
        $info = $this->autoPlanServiceV2->planInfo($planId);

        return $this->responseSuccess($info);
    }

    public function editPlan(string $appCode, int $planId)
    {
        $this->autoPlanServiceV2->editPlan($appCode, $planId, $this->request->all());

        return $this->responseSuccess();
    }

    public function enablePlan(string $appCode, int $planId)
    {
        $this->autoPlanServiceV2->enablePlan($planId);

        return $this->responseSuccess();
    }

    public function planLogs(string $appCode, int $planId)
    {
        $logList = $this->autoPlanServiceV2->planLogs($planId, $this->request->all());

        return $this->responsePaginator($logList->items(), $logList);
    }

    public function planLogInfo(string $appCode, int $planId, int $logId)
    {
        $logInfo = $this->autoPlanServiceV2->planLogInfo($logId);

        return $this->responseSuccess($logInfo);
    }
}