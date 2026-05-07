<?php


declare(strict_types=1);

namespace App\Command\Temp;

use App\Service\Cash\EcpmService;
use App\Service\Mkt\MktService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use App\Resource\Launch\LaunchAd\Advertiser;
use App\Service\Launch\AdvertiserService;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class AdPerformance extends HyperfCommand
{

    protected $name = 'temp:ad_performance';
    protected $advertiserService;
    protected $ecpmService;

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:ad_performance");
    }

    public function handle()
    {
        $this->line('Hello Hyperf!', 'info');
        $apps = make(MktService::class)->appListPutSelf();
        $this->advertiserService = make(AdvertiserService::class);
        $this->ecpmService = make(EcpmService::class);

        $now_date = date('Y-m-d');

        $keyMap = [
            "日期" => "date",
            "广告主ID" => "advertiser_id",
            "广告主名称" => "advertiser_name",
            "代理商" => "agency_name",
            "前日预算" => "before_budget",
            "当日预算" => "budget",
            "消耗" => "cost",
            "消耗达成率" => "budget_finish",
            "新增安装" => "install_num",
            "安装成本" => "install_pre_cost",
            "提现安装成本" => "install_withdraw",
            "新增安装ARPU" => "install_avg_revenue",
            "新增安装ROI" => "install_roi",
            "(充值)新增安装ROI" => "install_roi_payment",
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
            "激活10留率" => "keep10_rate",
        ];

        $hourly =  date("YmdH", time()-3600);
        foreach ($apps as $app => $name) {

            $params = [
                'app_code' => $app,
                'date_begin' => $now_date,
                'date_end' => $now_date,
                'page' => 1,
                'per_page' => 500,
            ];
            $this->line(json_encode($params), 'info');
            $result = $this->advertiserService->advertiser($params);
            $records = Advertiser::collection(object_to_arr(data_get($result, 'list')));

            $ecpmData = $this->ecpm($app);
            $currentEcpmData = $ecpmData[0]['installed_ecpm'];
            $preEcpmData = ($ecpmData[0]['installed_ecpm']+$ecpmData[1]['installed_ecpm']
                    +$ecpmData[2]['installed_ecpm'])/3;

            foreach ($records as $record) {
                $data = [
                    '记录时间' => date("Y-m-d H:i:s")
                ];
                $record = json_decode(json_encode($record), true);
                $this->line(json_encode($record), 'info');

                $data['时间'] = $hourly;
                $data['广告主ID'] = data_get($record, $keyMap['广告主ID']);
                $data['广告主名称'] = data_get($record, $keyMap['广告主名称']);
                $data['app_code'] = $app;
                $data['app_name'] = $name;

                $pre_advertiser_report = $this->pre_advertiser_report($data['广告主ID']);

                $data['roi'] = data_get($record, $keyMap['新增安装ROI']);
                $data['上周期roi'] = $pre_advertiser_report['新增安装ROI'];

                $keepRate = $this->get_keep1_rate($app, $data['广告主ID']);
                $data['前一天留存率'] = floatval(data_get($keepRate[0], 'keep1_rate'))/100;
                $data['近三天留存率'] = (floatval(data_get($keepRate[0], 'keep1_rate'))
                        + floatval(data_get($keepRate[1], 'keep1_rate'))
                        + floatval(data_get($keepRate[2], 'keep1_rate')))/3/100;

                $data['当日ecpm'] = $currentEcpmData;
                $data['近三天ecpm'] =  $preEcpmData;

                $data['当日预算'] = data_get($record, $keyMap['当日预算']);
                $data['消耗'] = data_get($record, $keyMap['消耗']);
                $data['消耗达成率'] = data_get($record, $keyMap['消耗达成率']);
                $data['上周期消耗达成率'] = $pre_advertiser_report['消耗达成率'];
                $float_diff = floatval($data['消耗达成率']) - floatval($data['上周期消耗达成率']);
                $data['上小时消耗百分比'] = sprintf('%.3f%%', $float_diff);

                $data['a1'] = $this->a1($data['roi']);
                $data['a2'] = $this->a2($data['roi'], $data['上周期roi']);
                $data['a3'] = $this->a3($data['前一天留存率'], $data['近三天留存率']);
                $data['a4'] = $this->a4($data['当日ecpm'], $data['近三天ecpm']);

                $data['summing'] = ($data['a1'] * 35 / 100
                    + $data['a2'] * 35 / 100
                    + $data['a3'] * 15 / 100
                    + $data['a4'] * 15 / 100);

                Db::table('ad_performance')->insert($data);
            }
        }
    }

    public function ecpm($app)
    {
        $ecpmParams = [
            "page" => 1,
            "per_page" => 10,
            "date_begin" =>  date('Y-m-d', strtotime('-4 day')),
            "date_end" => date('Y-m-d'),
        ];
        $ecpmData = $this->ecpmService->ecpmList($app, $ecpmParams);
        return $ecpmData;
    }

    public function pre_advertiser_report($advertiserId)
    {
        $list = Db::table("report_advertiser")->where('广告主ID', $advertiserId)
            ->orderBy('id', 'desc')
            ->limit(3)
            ->get()->toArray();
        $list = json_decode(json_encode($list), true);

        // 当前时间9：05，计算8点的数据，取前一个周期7点的数据，入库时间在8：05
        $match_date = date("Y-m-d H", time()-60*60*1);
        foreach ($list as $item) {
            if (strpos($item['记录时间'], $match_date) !== false) {
                return $item;
            }
        }
        return [];
    }

    public function get_keep1_rate($app, $advertiser_id)
    {
        $params = [
            'app_code' => $app,
            'advertiser_ids' => [$advertiser_id],
            "page" => 1,
            "per_page" => 10,
            "date_begin" => date('Y-m-d', strtotime('-4 day')),
            "date_end" =>  date('Y-m-d', strtotime('-1 day')),
        ];
        $result = $this->advertiserService->advertiser($params);
        $records = Advertiser::collection(object_to_arr(data_get($result, 'list')));
        $records = json_decode(json_encode($records), true);
        return $records;
    }

    public function a1($roi){
        $standard_roi = [
            6 => 25/100,
            7 => 27/100,
            8 => 30/100,
            9 => 33/100,
            10 => 36/100,
            11 => 38/100,
            12 => 40/100,
            13 => 42/100,
            14 => 42/100,
            15 => 44/100,
            16 => 46/100,
            17 => 48/100,
            18 => 50/100,
            19 => 53/100,
            20 => 57/100,
            21 => 60/100,
            22 => 63/100
        ];
        $hour =  date("H", time()-3600);
        $x = $roi - $standard_roi[intval($hour)];
        return 20*$x+3;
    }
    public function a2($roi, $pre_roi){
        return ($roi-$pre_roi)*200/3+5/3;
    }
    public function a3($keep1_rate, $pre_keep1_rate){
        if ($pre_keep1_rate == 0) {
            return 3;
        }
        return ($keep1_rate-$pre_keep1_rate)/$pre_keep1_rate*10+3;
    }
    public function a4($ecpm, $pre_ecpm){
        return ($ecpm-$pre_ecpm)/$pre_ecpm*10+3;
    }
}
