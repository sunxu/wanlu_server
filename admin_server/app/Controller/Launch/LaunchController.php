<?php

declare(strict_types=1);

namespace App\Controller\Launch;

use App\Constants\ErrorCode;
use App\Controller\AbstractController;
use App\Exception\BusinessException;
use App\Service\Launch\LaunchService;
use App\Service\Placement\LaunchBaseService;
use Hyperf\Di\Annotation\Inject;

class LaunchController extends AbstractController
{
    /**
     * @Inject
     * @var LaunchBaseService
     */
    protected $launchBaseService;

    /**
     * @Inject
     * @var LaunchService
     */
    protected $launchService;

    public function dailyLaunchList(string $appCode)
    {
        $params = $this->request->all();
        $result = $this->launchBaseService->list($params, $appCode);

        return $this->responsePaginator($result->items(), $result);
    }

    public function hourLaunchChart()
    {
        $params = $this->request->all();
        $dateBeginA = data_get($params, 'date_begin_a');
        $dateEndA = data_get($params, 'date_end_a');
        $dateBeginB = data_get($params, 'date_begin_b');
        $dateEndB = data_get($params, 'date_end_b');
        $timeDiffA = strtotime($dateEndA) - strtotime($dateBeginA);
        $timeDiffB = strtotime($dateEndB) - strtotime($dateBeginB);
        if ($timeDiffA != $timeDiffB) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '查询日期间隔不一致');
        }
        $result = $this->launchService->hourLaunchChart($params);

        return $this->responseSuccess($result);
    }
}