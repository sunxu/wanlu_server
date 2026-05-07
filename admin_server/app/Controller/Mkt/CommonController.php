<?php

declare(strict_types=1);

namespace App\Controller\Mkt;

use App\Controller\AbstractController;
use App\Model\Mkt\MktAdvertiser;
use App\Model\Mkt\MktPlan;
use Hyperf\DbConnection\Db;

class CommonController extends AbstractController
{
    public function getAdvertiser()
    {
        $params = $this->request->all();

        $appCode = data_get($params, 'app_code', 'app_code');
        $list = MktAdvertiser::query()
            ->select(['advertiser_id', Db::raw('CONCAT(advertiser_id,advertiser_name) as advertiser_name')])
            ->where('app_code', $appCode)
            ->get()
            ->toArray();

        return $this->responseSuccess([
            'list' => $list
        ]);
    }

    public function getAgencys(string $appCode)
    {
        $result = MktAdvertiser::query()
            ->select(['agency_name'])
            ->where('app_code', $appCode)
            ->where('agency_name', '<>', '')
            ->orderby('updated_time', 'desc')
            ->groupBy(['agency_name'])
            ->get()->all();

        return $this->responseRecord($result);
    }

    public function getAdvertiserPlan()
    {
        $params = $this->request->all();

        $appCode = data_get($params, 'app_code', 'app_code');
        $advertiserId = data_get($params, 'advertiser_id', 'advertiser_id');
        $list = MktPlan::query()
            ->select(['plan_id', Db::raw('CONCAT(plan_id,plan_name) as plan_name')])
            ->where('app_code', $appCode)
            ->where('advertiser_id', $advertiserId)
            ->get()
            ->toArray();

        return $this->responseSuccess([
            'list' => $list
        ]);
    }

    public function getAdvertiserProjectAd(string $appCode)
    {
        $result = MktAdvertiser::with(['project' => function ($query) {
                $query->select(['advertiser_id', 'project_id', 'project_name'])->with(['ad' => function ($query) {
                    $query->select(['project_id', 'ad_id', 'ad_name']);
                }]);
            }])
            ->select(['advertiser_id', 'advertiser_name'])->where('app_code', $appCode)
            ->orderby('updated_time', 'desc')->get()->all();

        return $this->responseRecord($result);
    }

    public function getAdvertiserProject(string $appCode)
    {
        $result = MktAdvertiser::with(['project' => function ($query) {
            $query->select(['advertiser_id', 'project_id', 'project_name']);
        }])
            ->select(['advertiser_id', 'advertiser_name'])->where('app_code', $appCode)
            ->orderby('updated_time', 'desc')->get()->all();

        return $this->responseRecord($result);
    }

    public function getAdvertiserWithPlan(string $appCode)
    {
        $result = MktAdvertiser::with(['plan' => function ($query) {
            $query->select(['advertiser_id', 'plan_id', 'plan_name']);
        }])
            ->select(['advertiser_id', 'advertiser_name'])->where('app_code', $appCode)
            ->orderby('updated_time', 'desc')->get()->all();

        return $this->responseRecord($result);
    }

    public function getAdvertisers(string $appCode)
    {
        $result = MktAdvertiser::select(['advertiser_id', 'advertiser_name'])
            ->where('app_code', $appCode)
            ->orderby('updated_time', 'desc')->get()->all();

        return $this->responseRecord($result);
    }
}