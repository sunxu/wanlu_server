<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\DbConnection\Db;
use Hyperf\HttpMessage\Stream\SwooleStream;

class JsonController extends AbstractController
{

    //    "page": 1,
    //    "per_page": 10,
    //    "app_code": "ddmf",
    //    "date_begin": "2025-10-10",
    //    "date_end": "2025-10-10",
    //    "platform": "jl",
    //    "launch_type": "0",
    //    "advertiser_ids": [],
    //    "agencys": []

    public function ad_performance()
    {
        $params = $this->request->all();

        var_dump(Db::enableQueryLog());
        $total = Db::table("ad_performance")
            ->when($appCode = data_get($params, 'app_code'), function ($query) use ($appCode) {
                $query->where('app_code', $appCode);
            })
            ->when($launch_type = data_get($params, 'launch_type'), function ($query) use ($launch_type) {
                $query->where('launch_type', $launch_type);
            })
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $dateValue = date('Ymd', strtotime($dateBegin)) . "00";
                $query->where('时间', '>=', intval($dateValue));
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $dateValue = date('Ymd', strtotime($dateEnd)) . "24";
                $query->where('时间', '<=', intval($dateValue));
            })
            ->when($advertiserIds = data_get($params, 'advertiser_ids'), function ($query) use ($advertiserIds) {
                $query->whereIn('广告主ID', $advertiserIds);
            })
            ->orderBy('id', 'desc')
            ->limit(24 * 32)
            ->get();
        var_dump(Db::getQueryLog());

        $currentPage = data_get($params, 'page', 1);
        $perPage = data_get($params, 'per_page', 10);
        $list = collect($total)->forPage($currentPage, $perPage)->values()->toArray();
        $list = json_decode(json_encode($list), true);

        foreach ($list as $k => $v) {
            $list[$k]['时间'] = $this->format_hourly($list[$k]['时间']);
        }

        return $this->responseSuccess([
            'list' => $list,
            'page_size' => $perPage,
            'current' => $currentPage,
            'total' => count($total),
        ]);
    }

    public function report_advertiser()
    {
        $params = $this->request->all();

        var_dump(Db::enableQueryLog());
        $total = Db::table("report_advertiser")
            ->when($appCode = data_get($params, 'app_code'), function ($query) use ($appCode) {
                $query->where('app_code', $appCode);
            })
            ->when($launch_type = data_get($params, 'launch_type'), function ($query) use ($launch_type) {
                $query->where('launch_type', $launch_type);
            })
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('记录时间', '>=', $dateBegin . " 00:00:00");
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('记录时间', '<=', $dateEnd . " 23:59:59");
            })
            ->when($advertiserIds = data_get($params, 'advertiser_ids'), function ($query) use ($advertiserIds) {
                $query->whereIn('广告主ID', $advertiserIds);
            })
            ->orderBy('id', 'desc')
            ->limit(5000)
            ->get();
        var_dump(Db::getQueryLog());

        $currentPage = data_get($params, 'page', 1);
        $perPage = data_get($params, 'per_page', 10);
        $list = collect($total)->forPage($currentPage, $perPage)->values()->toArray();
        $list = json_decode(json_encode($list), true);

        foreach ($list as $k => $v) {
            $list[$k]['日期'] = date("YmdH", strtotime($v['记录时间']));
            $list[$k]['日期'] = $this->format_hourly($list[$k]['日期']);
        }

        return $this->responseSuccess([
            'list' => $list,
            'page_size' => $perPage,
            'current' => $currentPage,
            'total' => count($total),
        ]);
    }

    public function hourly_advertiser()
    {
        $params = $this->request->all();

        $total = Db::table("report_hourly_advertiser")
            ->when($appCodes = data_get($params, 'app_codes'), function ($query) use ($appCodes) {
                $query->whereIn('app_code', $appCodes);
            })
            ->when($appCode = data_get($params, 'app_code'), function ($query) use ($appCode) {
                $query->where('app_code', $appCode);
            })
            ->when($launch_type = data_get($params, 'launch_type'), function ($query) use ($launch_type) {
                $query->where('launch_type', $launch_type);
            })
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('record_time', '>=', $dateBegin . " 00:00:00");
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('record_time', '<=', $dateEnd . " 23:59:59");
            })
            ->when($advertiserIds = data_get($params, 'advertiser_ids'), function ($query) use ($advertiserIds) {
                $query->whereIn('advertiser_id', $advertiserIds);
            })
            ->when($alertStatus = data_get($params, 'alert_status'), function ($query) use ($alertStatus) {
                if ($alertStatus !== '') {
                    $query->whereExists(function ($subQuery) use ($alertStatus) {
                        $subQuery->select(Db::raw(1))
                            ->from('mkt_advertiser')
                            ->whereColumn('mkt_advertiser.advertiser_id', 'report_hourly_advertiser.advertiser_id')
                            ->where('mkt_advertiser.alert_status', $alertStatus);
                    });
                }
            })
            ->orderBy('id', 'desc')
            ->limit(5000)
            ->get();

        $currentPage = data_get($params, 'page', 1);
        $perPage = data_get($params, 'per_page', 10);
        $list = collect($total)->forPage($currentPage, $perPage)->values()->toArray();
        $list = json_decode(json_encode($list), true);

        // Fetch alert_status from mkt_advertiser table
        if (!empty($list)) {
            $advertiserIds = array_column($list, 'advertiser_id');
            $advertisers = Db::table('mkt_advertiser')
                ->whereIn('advertiser_id', $advertiserIds)
                ->select(['advertiser_id', 'alert_status'])
                ->get()
                ->map(function ($v) {
                    return (array)$v;
                })
                ->toArray();
            $alertStatusMap = array_column($advertisers, 'alert_status', 'advertiser_id');

            foreach ($list as $k => $v) {
                $list[$k]['alert_status'] = $alertStatusMap[$v['advertiser_id']] ?? 0;
            }
        }

        return $this->responseSuccess([
            'list' => $list,
            'page_size' => $perPage,
            'current' => $currentPage,
            'total' => count($total),
        ]);
    }

    public function hourly_ad()
    {
        $params = $this->request->all();

        $total = Db::table("report_hourly_ad")
            ->when($appCodes = data_get($params, 'app_codes'), function ($query) use ($appCodes) {
                $query->whereIn('app_code', $appCodes);
            })
            ->when($appCode = data_get($params, 'app_code'), function ($query) use ($appCode) {
                $query->where('app_code', $appCode);
            })
            ->when($launch_type = data_get($params, 'launch_type'), function ($query) use ($launch_type) {
                $query->where('launch_type', $launch_type);
            })
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('record_time', '>=', $dateBegin . " 00:00:00");
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('record_time', '<=', $dateEnd . " 23:59:59");
            })
            ->when($advertiserIds = data_get($params, 'advertiser_ids'), function ($query) use ($advertiserIds) {
                $query->whereIn('advertiser_id', $advertiserIds);
            })
            ->when($ad_ids = data_get($params, 'ad_ids'), function ($query) use ($ad_ids) {
                $query->whereIn('ad_id', $ad_ids);
            })
            ->orderBy('id', 'desc')
            ->limit(5000)
            ->get();

        $currentPage = data_get($params, 'page', 1);
        $perPage = data_get($params, 'per_page', 10);
        $list = collect($total)->forPage($currentPage, $perPage)->values()->toArray();
        $list = json_decode(json_encode($list), true);

        $ad_ids = array_column($list, 'ad_id');
        $mkt_ads = Db::table("mkt_ad")->whereIn('ad_id', $ad_ids)
            ->select(['ad_id', 'put_status'])
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $put_status = array_column($mkt_ads, 'put_status', 'ad_id');
        foreach ($list as $k => $v) {
            $list[$k]['current_put_status'] = $put_status[$v['ad_id']];
        }

        return $this->responseSuccess([
            'list' => $list,
            'page_size' => $perPage,
            'current' => $currentPage,
            'total' => count($total),
        ]);
    }

    public function hourly_project()
    {
        $params = $this->request->all();

        $total = Db::table("report_hourly_project")
            ->when($appCodes = data_get($params, 'app_codes'), function ($query) use ($appCodes) {
                $query->whereIn('app_code', $appCodes);
            })
            ->when($appCode = data_get($params, 'app_code'), function ($query) use ($appCode) {
                $query->where('app_code', $appCode);
            })
            ->when($launch_type = data_get($params, 'launch_type'), function ($query) use ($launch_type) {
                $query->where('launch_type', $launch_type);
            })
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('record_time', '>=', $dateBegin . " 00:00:00");
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('record_time', '<=', $dateEnd . " 23:59:59");
            })
            ->when($advertiserIds = data_get($params, 'advertiser_ids'), function ($query) use ($advertiserIds) {
                $query->whereIn('advertiser_id', $advertiserIds);
            })
            ->when($ad_ids = data_get($params, 'project_ids'), function ($query) use ($ad_ids) {
                $query->whereIn('project_id', $ad_ids);
            })
            ->when($alertStatus = data_get($params, 'alert_status'), function ($query) use ($alertStatus) {
                if ($alertStatus !== '') {
                    $query->whereExists(function ($subQuery) use ($alertStatus) {
                        $subQuery->select(Db::raw(1))
                            ->from('mkt_project')
                            ->whereColumn('mkt_project.project_id', 'report_hourly_project.project_id')
                            ->where('mkt_project.alert_status', $alertStatus);
                    });
                }
            })
            ->orderBy('id', 'desc')
            ->limit(5000)
            ->get();

        $currentPage = data_get($params, 'page', 1);
        $perPage = data_get($params, 'per_page', 10);
        $list = collect($total)->forPage($currentPage, $perPage)->values()->toArray();
        $list = json_decode(json_encode($list), true);

        // Fetch alert_status from mkt_project table
        if (!empty($list)) {
            $projectIds = array_column($list, 'project_id');
            $projects = Db::table('mkt_project')
                ->whereIn('project_id', $projectIds)
                ->select(['project_id', 'alert_status'])
                ->get()
                ->map(function ($v) {
                    return (array)$v;
                })
                ->toArray();
            $alertStatusMap = array_column($projects, 'alert_status', 'project_id');

            foreach ($list as $k => $v) {
                $list[$k]['alert_status'] = $alertStatusMap[$v['project_id']] ?? 0;
            }
        }

        return $this->responseSuccess([
            'list' => $list,
            'page_size' => $perPage,
            'current' => $currentPage,
            'total' => count($total),
        ]);
    }

    public function hourly_filter() {
        $params = $this->request->all();
        $page = $params['page'];
        $type = $params['type'];
        $name = $type == "ad_id" ? 'ad_name' : 'advertiser_name';

        $total = Db::table("report_" . $page)
            ->when($appCodes = data_get($params, 'app_codes'), function ($query) use ($appCodes) {
                $query->whereIn('app_code', $appCodes);
            })
            ->when($appCode = data_get($params, 'app_code'), function ($query) use ($appCode) {
                $query->where('app_code', $appCode);
            })
            ->when($launch_type = data_get($params, 'launch_type'), function ($query) use ($launch_type) {
                $query->where('launch_type', $launch_type);
            })
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('record_time', '>=', $dateBegin);
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('record_time', '<=', $dateEnd);
            })
            ->when($advertiserIds = data_get($params, 'advertiser_ids'), function ($query) use ($advertiserIds) {
                $query->whereIn('advertiser_id', $advertiserIds);
            })
            ->when($projectIds = data_get($params, 'project_ids'), function ($query) use ($projectIds) {
                $query->whereIn('project_id', $projectIds);
            })
            ->groupBy([$type])
            ->select([$type, $name])
            ->get()
            ->map(function ($v) {return (array) $v;})
            ->toArray();
        return $this->responseSuccess([
            'filter' => $total,
        ]);
    }

    public function hourly_ad_filter() {
        $params = $this->request->all();
        $total = Db::table("report_hourly_ad")
            ->when($appCode = data_get($params, 'app_code'), function ($query) use ($appCode) {
                $query->where('app_code', $appCode);
            })
            ->when($launch_type = data_get($params, 'launch_type'), function ($query) use ($launch_type) {
                $query->where('launch_type', $launch_type);
            })
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('record_time', '>=', $dateBegin);
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('record_time', '<=', $dateEnd);
            })
            ->when($advertiserIds = data_get($params, 'advertiser_ids'), function ($query) use ($advertiserIds) {
                $query->whereIn('advertiser_id', $advertiserIds);
            })
            ->groupBy(['ad_id'])
            ->select(['ad_id', 'ad_name'])
            ->get()
            ->map(function ($v) {return (array) $v;})
            ->toArray();
        return $this->responseSuccess([
            'ad_filter' => $total,
        ]);
    }


    private function format_hourly($data) {
        $arr = str_split(strval($data), 2);
        return sprintf("%s%s-%s-%s %s", $arr[0], $arr[1], $arr[2], $arr[3], $arr[4]);
    }
}
