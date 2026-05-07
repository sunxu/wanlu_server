<?php

declare(strict_types=1);

namespace App\Controller\Put;

use App\Controller\AbstractController;
use App\Service\Mkt\AutoAppServiceV2;
use Hyperf\Di\Annotation\Inject;

class AutoAppControllerV2 extends AbstractController
{
    /**
     * @Inject
     * @var AutoAppServiceV2
     */
    protected $autoAppServiceV2;

    public function savePlan()
    {
        $this->autoAppServiceV2->savePlan($this->request->all());

        return $this->responseSuccess();
    }

    public function checkPlanConflict(string $appCode)
    {
        $params = $this->request->all();
        $advertiserIds = data_get($params, 'advertiser_ids', []);
        $thresholdKeys = data_get($params, 'threshold_keys');
        $planId = data_get($params, 'plan_id', 0);
//        $conflictList = $this->autoAppServiceV2->checkPlanConflict($appCode, $planId, $advertiserIds, $thresholdKeys);
        $conflictList = [];

        return $this->responseRecord($conflictList);
    }

    public function planList()
    {
        $list = $this->autoAppServiceV2->planList($this->request->all());

        return $this->responsePaginator($list->items(), $list);
    }

    public function planInfo(int $planId)
    {
        $info = $this->autoAppServiceV2->planInfo($planId);

        return $this->responseSuccess($info);
    }

    public function editPlan(int $planId)
    {
        $this->autoAppServiceV2->editPlan($planId, $this->request->all());

        return $this->responseSuccess();
    }

    public function enablePlan(int $planId)
    {
        $this->autoAppServiceV2->enablePlan($planId);

        return $this->responseSuccess();
    }

    public function planLogs(int $planId)
    {
        $logList = $this->autoAppServiceV2->planLogs($planId,
            $this->request->all());

        return $this->responsePaginator($logList->items(), $logList);
    }

    public function planLogInfo(int $planId, int $logId)
    {
        $logInfo = $this->autoAppServiceV2->planLogInfo($logId);

        return $this->responseSuccess($logInfo);
    }
}