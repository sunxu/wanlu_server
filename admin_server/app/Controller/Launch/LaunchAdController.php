<?php

declare(strict_types=1);

namespace App\Controller\Launch;

use App\Constants\ErrorCode;
use App\Controller\AbstractController;
use App\Exception\BusinessException;
use App\Request\Launch\LaunchAd\AdKeepRequest;
use App\Request\Launch\LaunchAd\ProjectRequest;
use App\Request\Launch\LaunchAd\AdRequest;
use App\Request\Launch\LaunchAd\ProjectKeepRequest;
use App\Request\Launch\LaunchAd\TotalRequest;
use App\Resource\Launch\LaunchAd\Ad;
use App\Resource\Launch\LaunchAd\AdCount;
use App\Resource\Launch\LaunchAd\AdKeep;
use App\Resource\Launch\LaunchAd\AdKeepCount;
use App\Resource\Launch\LaunchAd\Advertiser;
use App\Resource\Launch\LaunchAd\AdvertiserCount;
use App\Resource\Launch\LaunchAd\AdvertiserKeep;
use App\Resource\Launch\LaunchAd\AdvertiserKeepCount;
use App\Resource\Launch\LaunchAd\Plan;
use App\Resource\Launch\LaunchAd\PlanCount;
use App\Resource\Launch\LaunchAd\PlanKeep;
use App\Resource\Launch\LaunchAd\PlanKeepCount;
use App\Resource\Launch\LaunchAd\Project;
use App\Resource\Launch\LaunchAd\ProjectCount;
use App\Resource\Launch\LaunchAd\ProjectKeep;
use App\Resource\Launch\LaunchAd\ProjectKeepCount;
use App\Resource\Launch\LaunchAd\Total;
use App\Service\Launch\AdService;
use App\Service\Launch\AdvertiserService;
use App\Service\Launch\PlanService;
use App\Service\Launch\ProjectService;
use App\Service\Launch\TotalService;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;

class LaunchAdController extends AbstractController
{
    /**
     * @Inject
     * @var AdService
     */
    protected $adService;

    /**
     * @Inject
     * @var ProjectService
     */
    protected $projectService;

    /**
     * @Inject
     * @var AdvertiserService
     */
    protected $advertiserService;

    /**
     * @Inject
     * @var PlanService
     */
    protected $planService;

    /**
     * @Inject
     * @var TotalService
     */
    protected $totalService;

    public function ad(AdRequest $request)
    {
        $params = $request->all();
        if (strtotime(data_get($params, 'date_end')) - strtotime(data_get($params, 'date_begin')) > 60 * 60 * 24 * 30) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '查询日期应在 30 天以内');
        }
        $result = $this->adService->ad($params);

        return $this->responseSuccess([
            'total_data' => new AdCount(data_get($result, 'totalData')),
            'records' => Ad::collection(object_to_arr(data_get($result, 'list'))),
            'current' => data_get($result, 'current'),
            'page_size' => data_get($result, 'page_size'),
            'total' => data_get($result, 'total'),
        ]);
    }

    public function project(ProjectRequest $request)
    {
        $params = $request->all();
        if (strtotime(data_get($params, 'date_end')) - strtotime(data_get($params, 'date_begin')) > 60 * 60 * 24 * 30) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '查询日期应在 30 天以内');
        }
        $result = $this->projectService->project($params);

        return $this->responseSuccess([
            'total_data' => new ProjectCount(data_get($result, 'totalData')),
            'records' => Project::collection(object_to_arr(data_get($result, 'list'))),
            'current' => data_get($result, 'current'),
            'page_size' => data_get($result, 'page_size'),
            'total' => data_get($result, 'total'),
        ]);
    }

    public function advertiser()
    {
        $params = $this->request->all();
        if (strtotime(data_get($params, 'date_end')) - strtotime(data_get($params, 'date_begin')) > 60 * 60 * 24 * 30) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '查询日期应在 30 天以内');
        }
        $result = $this->advertiserService->advertiser($params);
        return $this->responseSuccess([
            'total_data' => new AdvertiserCount(data_get($result, 'totalData')),
            'records' => Advertiser::collection(object_to_arr(data_get($result, 'list'))),
            'current' => data_get($result, 'current'),
            'page_size' => data_get($result, 'page_size'),
            'total' => data_get($result, 'total'),
        ]);
    }

    public function plan()
    {
        $params = $this->request->all();
        if (strtotime(data_get($params, 'date_end')) - strtotime(data_get($params, 'date_begin')) > 60 * 60 * 24 * 30) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '查询日期应在 30 天以内');
        }
        $result = $this->planService->plan($params);
        return $this->responseSuccess([
            'total_data' => new PlanCount(data_get($result, 'totalData')),
            'records' => Plan::collection(object_to_arr(data_get($result, 'list'))),
            'current' => data_get($result, 'current'),
            'page_size' => data_get($result, 'page_size'),
            'total' => data_get($result, 'total'),
        ]);
    }

    public function adKeep(AdKeepRequest $request)
    {
        $params = $request->all();
        if (strtotime(data_get($params, 'date_end')) - strtotime(data_get($params, 'date_begin')) > 60 * 60 * 24 * 30) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '查询日期应在 30 天以内');
        }
        $result = $this->adService->adKeep($params);

        return $this->responseSuccess([
            'total_data' => new AdKeepCount(data_get($result, 'totalData')),
            'records' => AdKeep::collection(object_to_arr(data_get($result, 'list'))),
            'current' => data_get($result, 'current'),
            'page_size' => data_get($result, 'page_size'),
            'total' => data_get($result, 'total'),
        ]);
    }

    public function projectKeep(ProjectKeepRequest $request)
    {
        $params = $request->all();
        if (strtotime(data_get($params, 'date_end')) - strtotime(data_get($params, 'date_begin')) > 60 * 60 * 24 * 30) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '查询日期应在 30 天以内');
        }
        $result = $this->projectService->projectKeep($params);

        return $this->responseSuccess([
            'total_data' => new ProjectKeepCount(data_get($result, 'totalData')),
            'records' => ProjectKeep::collection(object_to_arr(data_get($result, 'list'))),
            'current' => data_get($result, 'current'),
            'page_size' => data_get($result, 'page_size'),
            'total' => data_get($result, 'total'),
        ]);
    }

    public function advertiserKeep()
    {
        $params = $this->request->all();
        if (strtotime(data_get($params, 'date_end')) - strtotime(data_get($params, 'date_begin')) > 60 * 60 * 24 * 30) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '查询日期应在 30 天以内');
        }
        $result = $this->advertiserService->advertiserKeep($params);
        return $this->responseSuccess([
            'total_data' => new AdvertiserKeepCount(data_get($result, 'totalData')),
            'records' => AdvertiserKeep::collection(object_to_arr(data_get($result, 'list'))),
            'current' => data_get($result, 'current'),
            'page_size' => data_get($result, 'page_size'),
            'total' => data_get($result, 'total'),
        ]);
    }

    public function planKeep()
    {
        $params = $this->request->all();
        if (strtotime(data_get($params, 'date_end')) - strtotime(data_get($params, 'date_begin')) > 60 * 60 * 24 * 30) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '查询日期应在 30 天以内');
        }
        $result = $this->planService->planKeep($params);
        return $this->responseSuccess([
            'total_data' => new PlanKeepCount(data_get($result, 'totalData')),
            'records' => PlanKeep::collection(object_to_arr(data_get($result, 'list'))),
            'current' => data_get($result, 'current'),
            'page_size' => data_get($result, 'page_size'),
            'total' => data_get($result, 'total'),
        ]);
    }

    public function total(TotalRequest $request)
    {
        $params = $request->all();
        if (strtotime(data_get($params, 'date_end')) - strtotime(data_get($params, 'date_begin')) > 60 * 60 * 24 * 30) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '查询日期应在 30 天以内');
        }

        $result = $this->totalService->total($params, (int)data_get(Context::get('_userInfo'), 'user_id'));

        return $this->responseSuccess([
            'total_data' => new Total(data_get($result, 'totalData')),
            'records' => Total::collection(object_to_arr(data_get($result, 'list'))),
            'current' => data_get($result, 'current'),
            'page_size' => data_get($result, 'page_size'),
            'total' => data_get($result, 'total'),
        ]);
    }
}