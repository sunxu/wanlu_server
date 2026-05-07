<?php

declare(strict_types=1);

namespace App\Controller\Put;

use App\Controller\AbstractController;
use App\Service\Mkt\AlertDelegateService;
use Hyperf\Di\Annotation\Inject;

class AlertDelegateController extends AbstractController
{
    /**
     * @Inject
     * @var AlertDelegateService
     */
    protected $alertDelegateService;

    /**
     * 创建代投配置
     */
    public function saveDelegate()
    {
        $delegate = $this->alertDelegateService->createDelegate($this->request->all());
        return $this->responseSuccess($delegate);
    }

    /**
     * 配置列表
     */
    public function delegateList()
    {
        $list = $this->alertDelegateService->delegateList($this->request->all());
        return $this->responsePaginator($list->items(), $list);
    }

    /**
     * 配置详情
     */
    public function delegateInfo(int $delegateId)
    {
        $info = $this->alertDelegateService->delegateInfo($delegateId);
        if (!$info) {
            return $this->responseError(-1, '配置不存在');
        }
        return $this->responseSuccess($info);
    }

    /**
     * 更新配置
     */
    public function updateDelegate(int $delegateId)
    {
        $this->alertDelegateService->updateDelegate($delegateId, $this->request->all());
        return $this->responseSuccess();
    }

    /**
     * 删除配置
     */
    public function deleteDelegate(int $delegateId)
    {
        $this->alertDelegateService->deleteDelegate($delegateId);
        return $this->responseSuccess();
    }

    /**
     * 测试钉钉
     */
    public function testDing(int $delegateId)
    {
        $result = $this->alertDelegateService->testDing($delegateId);
        return $this->responseSuccess(['result' => $result]);
    }
}
