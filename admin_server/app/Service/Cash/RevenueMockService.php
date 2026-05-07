<?php

declare(strict_types=1);

namespace App\Service\Cash;

use App\Model\RevenueMockVariable;
use Hyperf\DbConnection\Db;

class RevenueMockService
{
    protected $defaultMockVariable = [
        'variable1' => 36,
        'variable2' => 21,
        'variable3' => 17,
        'variable4' => 12,
        'variable5' => 8,
        'variable6' => 6,
        'variable7' => 5,
    ];

    /**
     * 获取计算模拟回收的计算比例
     * @param string $appCode
     * @return \Hyperf\Database\Model\Builder|\Hyperf\Database\Model\Model|int[]|object
     * @throws \Throwable
     */
    public function getMockVariables(string $appCode)
    {
        $variables = RevenueMockVariable::where('app_code', $appCode)
            ->select(['variable1', 'variable2', 'variable3', 'variable4', 'variable5', 'variable6', 'variable7'])->first();

        return $variables?:$this->defaultMockVariable;
    }

    public function editMockVariable(string $appCode, array $variables): void
    {
        $variablesInfo = RevenueMockVariable::where('app_code', $appCode)->first();
        if ( !$variablesInfo ) {
            $variablesInfo = new RevenueMockVariable();
            $variablesInfo->app_code = $appCode;
        }
        $variablesInfo->fill($variables);
        $variablesInfo->saveOrFail();
    }

    public function calculateMockResult($mockParams, $mockVariables)
    {
        $result = [];
        foreach ($mockParams as $item) {
            $installedCostAvg = data_get($item, 'installed_cost_avg');
            $installedArpu = data_get($item, 'installed_arpu');
            $oldArpu = data_get($item, 'old_arpu');
            $currentVar = 0;
            $currentResult = [];
            for($i = 1; $i <= 7; $i++) {
                $currentVar += data_get($mockVariables, "variable{$i}");
                $currentResult["mock_revenue{$i}"] = safe_div($installedArpu + $oldArpu * $currentVar / 100,$installedCostAvg);
            }
            $result[] = array_merge($item, $currentResult);
        }

        return collect($result)->sortByDesc('date')->toArray();
    }

    /**
     * 获取计算模拟回收的参数
     * @param string $appCode
     * @param array $params
     * @return mixed[]
     */
    public function getMockParams(string $appCode, array $params)
    {
        $data = Db::table('report_daily_advertiser as rdadv')
            ->leftJoin('report_daily_advertiser_ext as rdadve', 'rdadve.id', '=', 'rdadv.id')
            ->select([
                Db::raw('sum(rdadv.cost) as cost'), Db::raw('sum(rdadve.install_num) as install_num'),
                Db::raw('sum(rdadve.install_revenue) as install_revenue'), Db::raw('sum(rdadve.active_revenue) as active_revenue'),
                Db::raw('sum(rdadve.active_num) as active_num'), 'rdadv.date'
            ])
            ->where('app_code', $appCode)
            ->where('rdadv.date', '>=', data_get($params, 'date_begin'))
            ->where('rdadv.date', '<=', data_get($params, 'date_end'))
            ->groupBy('rdadv.date')
            ->get()->all();
        $result = [];
        for ($beginDate = strtotime(data_get($params, 'date_begin')); $beginDate <= strtotime(data_get($params, 'date_end'));) {
            $currentDay = date('Y-m-d', $beginDate);
            $currentData = collect($data)->where('date', $currentDay)->first();
            $result[] = [
                'date' => $currentDay,
                'installed_cost_avg' => safe_div(data_get($currentData, 'cost'), data_get($currentData, 'install_num')),
                'installed_roi' => safe_div(data_get($currentData, 'install_revenue'), data_get($currentData, 'cost')),
                'installed_arpu' => safe_div(data_get($currentData, 'install_revenue'), data_get($currentData, 'install_num')),
                'old_arpu' => safe_div(
                    data_get($currentData, 'active_revenue') - data_get($currentData, 'install_revenue'),
                    data_get($currentData, 'active_num') - data_get($currentData, 'install_num')
                ),
            ];
            $beginDate += 24 * 60 * 60;
        }
        return collect($result)->sortByDesc('date')->values()->toArray();
    }
}