<?php

declare(strict_types=1);

namespace App\Service\Paid;

use App\Constants\AppConst;
use App\Model\App;
use App\Model\Mkt\ReportDailyRecharge;
use Hyperf\DbConnection\Db;

class PaidService
{
    public function reportToday()
    {
        $this->reportDaily(date("Y-m-d"));
    }

    public function reportDaily(?string $reportDate = null)
    {
        $appCodes = [];
        $reportDate = $reportDate ?: date("Y-m-d", strtotime("yesterday"));
        $func = function ($appCode) use ($reportDate) {
            $tradeList = Db::connection('tools')->table("app_{$appCode}_trade")->where('trade_status', 1)
                ->whereBetween('created_time', [strtotime($reportDate), strtotime($reportDate) + 24 * 60 * 60 - 1])
                ->select(['goods_no', 'amount'])->get()->all();
            $goodsList = Db::connection('tools')->table('app_paid_goods')->select(['goods_no', 'goods_name'])
                ->get()->keyBy('goods_no')->all();
            ReportDailyRecharge::where('report_date', $reportDate)->where('app_code', $appCode)->delete();
            $model = new ReportDailyRecharge();
            $model->fill([
                'report_date' => $reportDate,
                'app_code' => $appCode,
                'total_amount' => collect($tradeList)->sum('amount'),
                'info' => collect($goodsList)->map(function ($item, $key) use ($tradeList) {
                    $amount = collect($tradeList)->where('goods_no', $key)->sum('amount');
                    data_set($item, 'amount', $amount);
                    return $item;
                })->all(),
            ]);
            $model->save();
        };
        array_map_safe($func, $appCodes);
    }

    public function reportDailyList(array $params = [])
    {
        return ReportDailyRecharge::when($appCode = data_get($params, 'app_code'), function ($query) use ($appCode) {
                $query->where('app_code', $appCode);
            })
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('report_date', '>=', $dateBegin);
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('report_date', '<=', $dateEnd);
            })
            ->orderBy('report_date', 'desc')->paginate(intval(data_get($params, 'per_page', 10)));
    }
}