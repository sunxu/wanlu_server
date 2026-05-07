<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Model\ReportDailyLaunchBase;
use App\Service\Placement\LaunchBaseService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class InputedRevenue extends HyperfCommand
{
    protected $name = 'temp:InputedRevenue';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:统计历史数据");
    }

    public function handle()
    {
        $this->info('Begin');
        ReportDailyLaunchBase::chunkById(10, function($data){
            foreach ($data as $value){
                $appCode = data_get($value, 'app_code');
                $beginTime = strtotime(data_get($value, 'created_date'));
                $endTime = $beginTime + 24 * 60 * 60 - 1;
                $id = data_get($value, 'id');
                $imputedRevenue = Db::connection('tools')->table("app_{$appCode}_event_interval_group as eig")
                    ->where('eig.start_time', '>=', $beginTime)
                    ->where('eig.end_time', '<=', $endTime)
                    ->sum("eig.amount");
                $model = ReportDailyLaunchBase::find($id);
                $model->active_revenue = $imputedRevenue;
                $model->save();
            }
            usleep(200*1000);
        });

        $this->info("End");
    }
}
