<?php

declare(strict_types=1);

namespace App\Controller\Put;

use App\Controller\AbstractController;
use App\Service\Mkt\AutoAppService;
use Hyperf\Di\Annotation\Inject;

class AutoAppController extends AbstractController
{
    /**
     * @Inject
     * @var AutoAppService
     */
    protected $autoAppService;

    public function savePlan()
    {
        $this->autoAppService->savePlan($this->request->all());

        return $this->responseSuccess();
    }

    public function planList()
    {
        $list = $this->autoAppService->planList($this->request->all());

        return $this->responsePaginator($list->items(), $list);
    }

    public function planInfo(int $planId)
    {
        $info = $this->autoAppService->planInfo($planId);

        return $this->responseSuccess($info);
    }

    public function editPlan(int $planId)
    {
        $this->autoAppService->editPlan($planId, $this->request->all());

        return $this->responseSuccess();
    }

    public function enablePlan(int $planId)
    {
        $this->autoAppService->enablePlan($planId);

        return $this->responseSuccess();
    }

    public function planLogs(int $planId)
    {
        $logList = $this->autoAppService->planLogs($planId, $this->request->all());

        return $this->responsePaginator($logList->items(), $logList);
    }

    public function planLogInfo(int $planId, int $logId)
    {
        $logInfo = $this->autoAppService->planLogInfo($logId);

        return $this->responseSuccess($logInfo);
    }
}