<?php

declare(strict_types=1);

namespace App\Service\Launch;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use Hyperf\DbConnection\Db;

class AdvertiserService
{

    public function advertiser_for_script(array $params)
    {
        $total = Db::table('report_daily_advertiser as rdadv')
            ->leftJoin('report_daily_advertiser_ext as rdadve', 'rdadve.id', '=', 'rdadv.id')
            ->leftJoin('mkt_advertiser as madv', 'madv.advertiser_id', '=', 'rdadv.advertiser_id')
            ->when($appCode = data_get($params, 'app_code'), function ($query) use ($appCode) {
                $query->where('rdadv.app_code', $appCode);
            })
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('rdadv.date', '>=', $dateBegin);
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('rdadv.date', '<=', $dateEnd);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('rdadv.platform', $platform);
            })
            ->when(!is_null($launchType = data_get($params, 'launch_type')), function ($query) use ($launchType) {
                $query->where('madv.launch_type', $launchType);
            })
            ->when($advertiserIds = data_get($params, 'advertiser_ids'), function ($query) use ($advertiserIds) {
                $query->whereIn('rdadv.advertiser_id', $advertiserIds);
            })
            ->when($agencys = data_get($params, 'agencys'), function ($query, $agencys) {
                $query->whereIn('madv.agency_name', $agencys);
            })
            ->when($agencyProxy = data_get($params, 'agency_proxy'), function ($query) use ($agencyProxy) {
                $query->where('madv.advertiser_name', 'like', "%{$agencyProxy}%");
            })
            ->select([
                'rdadv.date', 'rdadv.advertiser_id', 'madv.advertiser_name', 'rdadv.cost', 'rdadv.active_cost',
                'rdadv.key_action_cost', 'rdadv.key_action_rate', 'rdadve.withdraw', 'rdadve.install_payment', 'rdadve.install_num',
                'rdadve.install_avg_revenue', 'rdadve.install_revenue', 'rdadve.install_revenue24', 'rdadv.key_action',
                'rdadv.active', 'rdadve.recovery', 'rdadve.activate_keep', 'madv.agency_name', 'rdadv.budget',
                'madv.launch_type', 'madv.active_type', 'madv.status',
                Db::raw('rdadv.*'),
            ])
            ->orderBy('rdadv.date', 'desc')->orderBy('rdadv.id', 'desc')->get()->map(function ($item){
                $activateKeep = json_decode(data_get($item, 'activate_keep', '[]'), true);
                $recovery = json_decode(data_get($item, 'recovery', '[]'), true);
                for ($day = 0; $day <= 10; $day++) {
                    $next = $day + 1;
                    if (isset($recovery["recovery{$day}"]) && isset($recovery["recovery{$next}"])) {
                        $revenue = round(data_get($recovery["recovery{$day}"], 'revenue', 0) + data_get($recovery["recovery{$next}"], 'revenue', 0), 2);
                        data_set($recovery["recovery{$next}"], 'revenue', $revenue);
                        data_set($recovery["recovery{$next}"], 'recovery_rate', safe_div($revenue * 100, data_get($recovery["recovery{$next}"], 'cost', 0), 2) . '%');
                    }
                }
                data_set($item, 'activate_keep', $activateKeep);
                data_set($item, 'recovery', $recovery);
                return $item;
            });
        $total = $total->map(function ($v) {return (array) $v;})->all();
        return $total;
    }

    public function advertiser(array $params)
    {
        $total = Db::table('report_daily_advertiser as rdadv')
            ->leftJoin('report_daily_advertiser_ext as rdadve', 'rdadve.id', '=', 'rdadv.id')
            ->leftJoin('mkt_advertiser as madv', 'madv.advertiser_id', '=', 'rdadv.advertiser_id')
            ->when($appCode = data_get($params, 'app_code'), function ($query) use ($appCode) {
                $query->where('rdadv.app_code', $appCode);
            })
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('rdadv.date', '>=', $dateBegin);
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('rdadv.date', '<=', $dateEnd);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('rdadv.platform', $platform);
            })
            ->when(!is_null($launchType = data_get($params, 'launch_type')), function ($query) use ($launchType) {
                $query->where('madv.launch_type', $launchType);
            })
            ->when($advertiserIds = data_get($params, 'advertiser_ids'), function ($query) use ($advertiserIds) {
                $query->whereIn('rdadv.advertiser_id', $advertiserIds);
            })
            ->when($agencys = data_get($params, 'agencys'), function ($query, $agencys) {
                $query->whereIn('madv.agency_name', $agencys);
            })
            ->when($agencyProxy = data_get($params, 'agency_proxy'), function ($query) use ($agencyProxy) {
                $query->where('madv.advertiser_name', 'like', "%{$agencyProxy}%");
            })
            ->select([
                'rdadv.date', 'rdadv.advertiser_id', 'madv.advertiser_name', 'rdadv.cost', 'rdadv.active_cost',
                'rdadv.key_action_cost', 'rdadv.key_action_rate', 'rdadve.withdraw', 'rdadve.install_payment', 'rdadve.install_num',
                'rdadve.install_avg_revenue', 'rdadve.install_revenue', 'rdadve.install_revenue24', 'rdadv.key_action',
                'rdadv.active', 'rdadve.recovery', 'rdadve.activate_keep', 'madv.agency_name', 'rdadv.budget',
                Db::raw("(select rdadvtemp.budget as before_budget from report_daily_advertiser as rdadvtemp where " .
                    "rdadvtemp.date = DATE_SUB(rdadv.date,INTERVAL 1 DAY) and rdadvtemp.app_code = rdadv.app_code and " .
                    "rdadvtemp.advertiser_id = rdadv.advertiser_id) as before_budget")
            ])
            ->orderBy('rdadv.date', 'desc')->orderBy('rdadv.id', 'desc')->get()->map(function ($item){
                $activateKeep = json_decode(data_get($item, 'activate_keep', '[]'), true);
                $recovery = json_decode(data_get($item, 'recovery', '[]'), true);
                for ($day = 0; $day <= 10; $day++) {
                    $next = $day + 1;
                    if (isset($recovery["recovery{$day}"]) && isset($recovery["recovery{$next}"])) {
                        $revenue = round(data_get($recovery["recovery{$day}"], 'revenue', 0) + data_get($recovery["recovery{$next}"], 'revenue', 0), 2);
                        data_set($recovery["recovery{$next}"], 'revenue', $revenue);
                        data_set($recovery["recovery{$next}"], 'recovery_rate', safe_div($revenue * 100, data_get($recovery["recovery{$next}"], 'cost', 0), 2) . '%');
                    }
                }
                data_set($item, 'activate_keep', $activateKeep);
                data_set($item, 'recovery', $recovery);
                return $item;
            });
        if ($sortColumn = data_get($params, 'sort_column')) {
            if(data_get($params, 'sort_type') == 'descending'){
                $total = $total->sortByDesc($this->sortAdvertiser($sortColumn));
            }else{
                $total = $total->sortBy($this->sortAdvertiser($sortColumn));
            }
        }
        $total = $total->all();
        $currentPage = data_get($params, 'page', 1);
        $perPage = data_get($params, 'per_page', 10);
        $list = collect($total)->forPage($currentPage, $perPage)->toArray();

        return [
            'totalData' => $this->countAdvertiserData($total),
            'list' => $list,
            'page_size' => count($list),
            'current' => $currentPage,
            'total' => count($total),
        ];
    }

    public function sortAdvertiser(string $column)
    {
        return function ($item) use ($column) {
            switch ($column){
                case 'cost':
                case 'withdraw':
                case 'install_payment':
                case 'install_num':
                case 'active':
                case 'active_cost':
                case 'budget':
                case 'before_budget':
                    return data_get($item, $column, 0);
                case 'install_pre_cost':
                    return data_get($item, 'install_num') <= 0 ? 0 : data_get($item, 'cost') / data_get($item, 'install_num');
                case 'install_withdraw':
                    return data_get($item, 'install_num') <= 0 ? 0 : (data_get($item, 'cost') + data_get($item, 'withdraw')) / data_get($item, 'install_num');
                case 'install_roi':
                    return data_get($item, 'cost') <= 0 ? 0 : data_get($item, 'install_revenue') / data_get($item, 'cost');
                case 'install_roi_withdraw':
                    return (data_get($item, 'cost') + data_get($item, 'withdraw')) <= 0 ? 0 : data_get($item, 'install_revenue') / (data_get($item, 'cost') + data_get($item, 'withdraw'));
                case 'install_roi_payment':
                    return (data_get($item, 'cost') - data_get($item, 'install_payment')) <= 0 ? 0 : data_get($item, 'install_revenue') / (data_get($item, 'cost') - data_get($item, 'install_payment'));
                case 'install_roi_24h':
                    return data_get($item, 'cost') <= 0 ? 0 : data_get($item, 'install_revenue24') / data_get($item, 'cost');
                case 'install_roi_24h_withdraw':
                    return (data_get($item, 'cost') + data_get($item, 'withdraw')) <= 0 ? 0 : data_get($item, 'install_revenue24') / (data_get($item, 'cost') + data_get($item, 'withdraw'));
                case 'budget_finish':
                    return data_get($item, 'budget') <= 0 ? 0 :data_get($item, 'cost') / data_get($item, 'budget');
                default:
                    throw new BusinessException(ErrorCode::SERVER_ERROR, '不存在的排序方式');
            }
        };
    }

    public function countAdvertiserData($data): array
    {
        return [
            'cost' => collect($data)->sum('cost'),
            'before_budget' => collect($data)->sum('before_budget'),
            'budget' => collect($data)->sum('budget'),
            'install_payment' => collect($data)->sum('install_payment'),
            'withdraw' => collect($data)->sum('withdraw'),
            'install_num' => collect($data)->sum('install_num'),
            'install_revenue' => collect($data)->sum('install_revenue'),
            'install_revenue24' => collect($data)->sum('install_revenue24'),
            'active' => collect($data)->sum('active'),
            'key_action' => collect($data)->sum('key_action'),
            'cost3' => collect($data)->sum(function ($item) {
                return data_get($item, 'recovery.recovery3.cost');
            }),
            'revenue3' => collect($data)->sum(function ($item) {
                return data_get($item, 'recovery.recovery3.revenue');
            }),
            'cost7' => collect($data)->sum(function ($item) {
                return data_get($item, 'recovery.recovery7.cost');
            }),
            'revenue7' => collect($data)->sum(function ($item) {
                return data_get($item, 'recovery.recovery7.revenue');
            }),
            'cost10' => collect($data)->sum(function ($item) {
                return data_get($item, 'recovery.recovery10.cost');
            }),
            'revenue10' => collect($data)->sum(function ($item) {
                return data_get($item, 'recovery.recovery10.revenue');
            }),
            'keep1_num' => collect($data)->sum(function ($item) {
                return data_get($item, 'activate_keep.keep1.keep_num');
            }),
            'ago1_num' => collect($data)->sum(function ($item) {
                return data_get($item, 'activate_keep.keep1.ago_num');
            }),
            'keep3_num' => collect($data)->sum(function ($item) {
                return data_get($item, 'activate_keep.keep3.keep_num');
            }),
            'ago3_num' => collect($data)->sum(function ($item) {
                return data_get($item, 'activate_keep.keep3.ago_num');
            }),
            'keep7_num' => collect($data)->sum(function ($item) {
                return data_get($item, 'activate_keep.keep7.keep_num');
            }),
            'ago7_num' => collect($data)->sum(function ($item) {
                return data_get($item, 'activate_keep.keep7.ago_num');
            }),
            'keep10_num' => collect($data)->sum(function ($item) {
                return data_get($item, 'activate_keep.keep10.keep_num');
            }),
            'ago10_num' => collect($data)->sum(function ($item) {
                return data_get($item, 'activate_keep.keep10.ago_num');
            }),
        ];
    }

    public function countAdvertiserKeepData($data): array
    {
        return [
            'install_num' => collect($data)->sum('install_num'),
            'activate_num' => collect($data)->sum('activate_num'),
            'active' => collect($data)->sum('active'),
            'keep1_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep1.keep_num');}),
            'ago1_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep1.ago_num');}),
            'keep2_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep2.keep_num');}),
            'ago2_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep2.ago_num');}),
            'keep3_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep3.keep_num');}),
            'ago3_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep3.ago_num');}),
            'keep4_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep4.keep_num');}),
            'ago4_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep4.ago_num');}),
            'keep5_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep5.keep_num');}),
            'ago5_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep5.ago_num');}),
            'keep6_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep6.keep_num');}),
            'ago6_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep6.ago_num');}),
            'keep7_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep7.keep_num');}),
            'ago7_num' => collect($data)->sum(function ($item) {return data_get($item, 'install_keep.keep7.ago_num');}),
            'revenue0' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery0.revenue');}),
            'cost0' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery0.cost');}),
            'revenue1' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery1.revenue');}),
            'cost1' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery1.cost');}),
            'revenue2' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery2.revenue');}),
            'cost2' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery2.cost');}),
            'revenue3' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery3.revenue');}),
            'cost3' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery3.cost');}),
            'revenue4' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery4.revenue');}),
            'cost4' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery4.cost');}),
            'revenue5' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery5.revenue');}),
            'cost5' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery5.cost');}),
            'revenue6' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery6.revenue');}),
            'cost6' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery6.cost');}),
            'revenue7' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery7.revenue');}),
            'cost7' => collect($data)->sum(function ($item) {return data_get($item, 'recovery.recovery7.cost');}),
        ];
    }

    public function advertiserKeep(array $params)
    {
        $total = Db::table('report_daily_advertiser as rdadv')
            ->leftJoin('report_daily_advertiser_ext as rdadve', 'rdadve.id', '=', 'rdadv.id')
            ->leftJoin('mkt_advertiser as madv', 'madv.advertiser_id', '=', 'rdadv.advertiser_id')
            ->when($appCode = data_get($params, 'app_code'), function ($query) use ($appCode) {
                $query->where('rdadv.app_code', $appCode);
            })
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('rdadv.date', '>=', $dateBegin);
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('rdadv.date', '<=', $dateEnd);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('rdadv.platform', $platform);
            })
            ->when(!is_null($launchType = data_get($params, 'launch_type')), function ($query) use ($launchType) {
                $query->where('madv.launch_type', $launchType);
            })
            ->when($advertiserIds = data_get($params, 'advertiser_ids'), function ($query) use ($advertiserIds) {
                $query->whereIn('rdadv.advertiser_id', $advertiserIds);
            })
            ->select([
                'rdadv.date', 'rdadv.advertiser_id', 'rdadv.active', 'rdadve.install_num', 'rdadve.active_num',
                'rdadve.activate_num', 'rdadve.install_keep', 'rdadve.recovery', 'madv.advertiser_name',
            ])
            ->orderBy('rdadv.date', 'desc')->orderBy('rdadv.id', 'desc')->get()->map(function ($item) {
                $installKeep = json_decode(data_get($item, 'install_keep', '[]'), true);
                $recovery = json_decode(data_get($item, 'recovery', '[]'), true);
                for ($day = 0; $day <= 10; $day++) {
                    $next = $day + 1;
                    if (isset($recovery["recovery{$day}"]) && isset($recovery["recovery{$next}"])) {
                        $revenue = round(data_get($recovery["recovery{$day}"], 'revenue', 0) + data_get($recovery["recovery{$next}"], 'revenue', 0), 2);
                        data_set($recovery["recovery{$next}"], 'revenue', $revenue);
                        data_set($recovery["recovery{$next}"], 'recovery_rate', safe_div($revenue * 100, data_get($recovery["recovery{$next}"], 'cost', 0), 2) . '%');
                    }
                }
                data_set($item, 'install_keep', $installKeep);
                data_set($item, 'recovery', $recovery);
                return $item;
            })->all();
        $currentPage = data_get($params, 'page', 1);
        $perPage = data_get($params, 'per_page', 10);
        $list = collect($total)->forPage($currentPage, $perPage)->toArray();

        return [
            'totalData' => $this->countAdvertiserKeepData($total),
            'list' => $list,
            'page_size' => count($list),
            'current' => $currentPage,
            'total' => count($total),
        ];
    }
}