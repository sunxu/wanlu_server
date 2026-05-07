<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Constants\MediaConst;
use App\Model\Media\MediaDailyApp;
use App\Model\Media\MediaDailyCompany;
use App\Model\Media\MediaDailyLianyun;

class TotalService
{
    protected $mediaType = MediaConst::MEDIA_TYPE;

    public function app($params)
    {
        $model = $params['put'] == 'agency' ? MediaDailyLianyun::query() : MediaDailyApp::query();
        $total = $model
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('date', '>=', $dateBegin);
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('date', '<=', $dateEnd);
            })
            ->orderBy('date', 'desc')
            ->get()
            ->all();
        collect($total)->map(function ($item) {
            $mediaType = [];
            foreach (data_get($item, 'media_type', []) as $type) {
                $mediaType[] = $this->mediaType[$type];
            }
            data_set($item, 'media_type', implode(',', $mediaType));
            data_set($item, 'detail', array_values(data_get($item, 'detail', [])));
            return $item;
        });
        $currentPage = data_get($params, 'page', 1);
        $perPage = data_get($params, 'per_page', 10);
        $list = collect($total)->forPage((int)$currentPage, (int)$perPage)->toArray();
        array_unshift($list, $this->appTotal($total));
        return [
            'records' => $list,
            'page_size' => $perPage,
            'current' => $currentPage,
            'total' => count($total),
        ];
    }

    public function company($params)
    {
        $total = MediaDailyCompany::query()
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('date', '>=', $dateBegin);
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('date', '<=', $dateEnd);
            })
            ->orderBy('date', 'desc')
            ->get()
            ->all();

        $currentPage = data_get($params, 'page', 1);
        $perPage = data_get($params, 'per_page', 10);
        $list = collect($total)->forPage((int)$currentPage, (int)$perPage)->toArray();
        array_unshift($list, $this->companyTotal($total));

        foreach ($list as &$item) {
            $totalMedia = [];
            foreach (data_get($item, 'detail', []) as $company) {
                foreach (data_get($company, 'detail', []) as $type => $media) {
                    if (!isset($totalMedia[$type])) {
                        $totalMedia[$type] = $media;
                    } else {
                        $totalMedia[$type]['revenue'] = round(($totalMedia[$type]['revenue'] ?? 0) + data_get($media, 'revenue', 0), 2);
                    }
                }
            }
            data_set($item, 'total_media', $totalMedia);
        }
        unset($item);

        return [
            'records' => $list,
            'page_size' => $perPage,
            'current' => $currentPage,
            'total' => count($total),
        ];
    }

    protected function companyTotal(array $data)
    {
        $detail = [];
        $totalRevenue = 0;
        foreach ($data as $row) {
            $totalRevenue = round($totalRevenue + data_get($row, 'total_revenue', 0), 2);
            foreach (data_get($row, 'detail') as $item) {
                $companyId = data_get($item, 'company_id', 0);
                if (!isset($detail[$companyId])) {
                    $detail[$companyId] = [
                        'company_id' => $companyId,
                        'company_name' => data_get($item, 'company_name', ''),
                        'company_revenue' => 0,
                        'detail' => []
                    ];
                }
                foreach (data_get($item, 'detail', []) as $t => $d) {
                    if (!isset($detail[$companyId]['detail'][$t])) {
                        $detail[$companyId]['detail'][$t] = [
                            'media_name' => data_get($d, 'media_name', ''),
                            'media_type' => data_get($d, 'media_type', ''),
                            'revenue' => 0,
                        ];
                    }
                    $revenue = data_get($d, 'revenue', 0);
                    $detail[$companyId]['company_revenue'] = round($detail[$companyId]['company_revenue'] + $revenue, 2);
                    $detail[$companyId]['detail'][$t]['revenue'] = round($detail[$companyId]['detail'][$t]['revenue'] + $revenue, 2);
                }
            }
        }
        return [
            'date' => '总计',
            'total_revenue' => $totalRevenue,
            'detail' => array_values($detail)
        ];
    }

    protected function appTotal(array $data)
    {
        $detail = [];
        foreach ($data as $row) {
            foreach (data_get($row, 'detail') as $item) {
                $appCode = data_get($item, 'app_code', 0);
                if (!isset($detail[$appCode])) {
                    $detail[$appCode] = [
                        'app_code' => $appCode,
                        'app_name' => data_get($item, 'app_name', ''),
                        'revenue' => 0,
                        'cost' => 0,
                        'roi' => 0
                    ];
                }

                $detail[$appCode]['revenue'] = round($detail[$appCode]['revenue'] + data_get($item, 'revenue', 0), 2);
                $detail[$appCode]['cost'] = round($detail[$appCode]['cost'] + data_get($item, 'cost', 0), 2);
                $detail[$appCode]['roi'] = $detail[$appCode]['cost'] > 0 ? round($detail[$appCode]['revenue'] / $detail[$appCode]['cost'], 2) : 0;
            }
        }
        return [
            'date' => '总计',
            'media_type' => '',
            'media_revenue' => round(collect($data)->sum('media_revenue'), 2),
            'other_revenue' => round(collect($data)->sum('other_revenue'), 2),
            'total_cost' => round(collect($data)->sum('total_cost'), 2),
            'total_revenue' => round(collect($data)->sum('total_revenue'), 2),
            'detail' => array_values($detail)
        ];
    }
}