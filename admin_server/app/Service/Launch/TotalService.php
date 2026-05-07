<?php

declare(strict_types=1);

namespace App\Service\Launch;

use App\Constants\ErrorCode;
use App\Constants\UserConst;
use App\Exception\BusinessException;
use App\Service\AppService;
use App\Service\UserService;
use Hyperf\DbConnection\Db;

class TotalService
{
    public function total(array $params, int $userId)
    {
        $userInfo = make(UserService::class)->getUserInfo($userId);

        $total = Db::table('report_daily_advertiser as rda')
            ->leftJoin('mkt_advertiser as ma', 'ma.advertiser_id', '=', 'rda.advertiser_id')
            ->leftJoin('report_daily_advertiser_ext as rdae', 'rdae.id', '=', 'rda.id')
            ->leftJoin('app', 'app.app_code', '=', 'rda.app_code')
            ->when(data_get($userInfo, 'is_super') != UserConst::USER_TYPE['super'], function ($query) use ($userId) {
                $userApp = make(AppService::class)->getUserApp($userId);
                $query->whereIn('rda.app_code', array_keys($userApp));
            })
            ->when(!empty($appCode = data_get($params, 'app_code')), function ($query) use ($appCode) {
                $query->whereIn('rda.app_code', $appCode);
            })
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('rda.date', '>=', $dateBegin);
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('rda.date', '<=', $dateEnd);
            })
            ->when($platform = data_get($params, 'platform'), function ($query) use ($platform) {
                $query->where('rda.platform', $platform);
            })
            ->when(in_array($launchType = data_get($params, 'launch_type'), ['0', '1']), function ($query) use ($launchType) {
                $query->where('ma.launch_type', $launchType);
            })
            ->select([
                'rda.date', Db::raw('sum(rda.cost) as cost'), Db::raw('sum(rdae.install_num) as install_num'), Db::raw('sum(rda.active) as active'),
                Db::raw('sum(rda.key_action) as key_action'), Db::raw('count(rda.advertiser_id) as advertiser_num'), 'app.app_name', Db::raw('sum(rdae.install_revenue) as install_revenue'), Db::raw('sum(rdae.install_revenue24) as install_revenue24')
            ])
            ->groupBy(['rda.date', 'rda.app_code'])
            ->orderBy('date', 'desc')
            ->orderBy('cost', 'desc')
            ->get()
            ->all();

        $currentPage = data_get($params, 'page', 1);
        $perPage = data_get($params, 'per_page', 10);
        $list = collect($total)->forPage((int)$currentPage, (int)$perPage)->toArray();

        return [
            'totalData' => $this->totalData($total),
            'list' => $list,
            'page_size' => count($list),
            'current' => $currentPage,
            'total' => count($total),
        ];
    }

    public function totalData($data): array
    {
        return [
            'cost' => collect($data)->sum('cost'),
            'install_num' => collect($data)->sum('install_num'),
            'install_revenue' => collect($data)->sum('install_revenue'),
            'install_revenue24' => collect($data)->sum('install_revenue24'),
            'active' => collect($data)->sum('active'),
            'key_action' => collect($data)->sum('key_action'),
            'advertiser_num' => collect($data)->sum('advertiser_num'),
        ];
    }
}