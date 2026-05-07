<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Service\Mkt\MktService;
use App\ModelTools\App;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use App\Resource\Launch\LaunchAd\Advertiser;
use App\Resource\Launch\LaunchAd\Ad;
use App\Service\Launch\AdService;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class ReportAd extends HyperfCommand
{

    protected $name = 'temp:report_ad';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:同步ad");
    }

    public function handle()
    {
        $this->line('Hello Hyperf!', 'info');
        $apps = make(MktService::class)->appListPutSelf();
        $adService = make(AdService::class);

        $now_date = date('Y-m-d');

        $keyMap = [
"日期" => "date",
"广告ID" => "ad_id",
"广告名称" => "ad_name",
"广告主ID" => "advertiser_id",
"广告主名称" => "advertiser_name",
"消耗" => "cost",
"提现金额(当日)" => "withdraw",
"充值(当日)" => "install_payment",
"新增安装" => "install_num",
"安装成本" => "install_pre_cost",
"提现安装成本" => "install_withdraw",
"新增安装ARPU" => "install_avg_revenue",
"新增安装ROI" => "install_roi",
"(提现)新增安装ROI" => "install_roi_withdraw",
"24H新增安装ROI" => "install_roi_24h",
"(提现)24H新增安装ROI" => "install_roi_24h_withdraw",
"激活成本" => "active_cost",
"关键行为成本" => "key_action_cost",
"关键行为率" => "key_action_rate",
"新增激活回传数" => "active",
"关键行为数" => "key_action",
"3日回本" => "recovery3_rate",
"7日回本" => "recovery7_rate",
"10日回本" => "recovery10_rate",
"激活次留率" => "keep1_rate",
"激活3留率" => "keep3_rate",
"激活7留率" => "keep7_rate",
"激活10留率" => "keep10_rate"
        ];

        foreach ($apps as $app => $name){

            $params = [
                'app_code' => $app,
                'date_begin' => $now_date,
                'date_end' => $now_date,
                'page' => 1,
                'per_page' => 500,
            ];
            $this->line(json_encode($params), 'info');
            $result = $adService->ad($params);
            $records = Ad::collection(object_to_arr(data_get($result, 'list')));

            foreach($records as $record) {
                $data = [
                    '记录时间' => date("Y-m-d H:i:s"),
                    'app_code' => $app,
                ];
                $record = json_decode(json_encode($record), true);
                $this->line(json_encode($record), 'info');
                foreach($keyMap as $key => $value) {
                    $data[$key] = data_get($record, $value);
                }

                Db::table('report_ad')->insert($data);
            }
        }
    }
}
