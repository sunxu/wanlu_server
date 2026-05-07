<?php

declare(strict_types=1);

namespace App\Controller\Cash;

use App\Controller\AbstractController;
use App\Resource\Cash\RevenueList;
use App\Service\Cash\RevenueMockService;
use App\Service\Cash\RevenueService;
use Hyperf\Di\Annotation\Inject;

class RevenueController extends AbstractController
{
    /**
     * @Inject
     * @var RevenueService
     */
    protected $revenueService;

    /**
     * @Inject
     * @var RevenueMockService
     */
    protected $revenueMockService;

    public function index(string $appCode)
    {
        $params = $this->request->all();
        $data = $this->revenueService->revenueList($appCode, $params);

        return $this->responsePaginator(RevenueList::collection(object_to_arr($data->items())), $data);
    }

    public function mockRevenue(string $appCode)
    {
        $variables = $this->revenueMockService->getMockVariables($appCode);
        $params = $this->revenueMockService->getMockParams($appCode, $this->request->all());
        $result = $this->revenueMockService->calculateMockResult($params, $variables);

        return $this->responseRecord($result);
    }

    public function mockVariables(string $appCode)
    {
        $variables = $this->revenueMockService->getMockVariables($appCode);

        return $this->responseSuccess($variables);
    }

    public function editMockVariables(string $appCode)
    {
        $this->revenueMockService->editMockVariable($appCode, $this->request->all());

        return $this->responseSuccess();
    }
}