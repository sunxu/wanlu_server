<?php

namespace App\Console\Commands\Ks;

use App\Services\Common\KsReport;
use App\Services\Common\ZengZhangCanMou;
use Illuminate\Console\Command;

class CreativeDailyKs extends Command
{
    /**
     * @var string Command Name
     */
    protected $signature = "creative-daily-ks";

    /**
     * Command description
     * @var string
     */
    protected $description = "快手广告创意维度投放天级数据";

    /**
     * Command Handle
     */
    public function handle()
    {
        $temporal_granularity = 'DAILY';
        $start_date_min = date('Y-m-d', strtotime('-1 day')) . ' 00:00';//昨天
        $end_date_min = date('Y-m-d') . ' 00:00';//今天
        $appcodes = array_keys(ZengZhangCanMou::PUT_CONFIG);
        foreach ($appcodes as $appcode) {
            /*拉取快手创意数据*/
            $details = KsReport::getInstance()->setAppCode($appcode)->CreativeReport($temporal_granularity, $start_date_min, $end_date_min);
            /*上报增长参谋*/
            $payloads = [];
            foreach ($details as $detail) {
                $payload = [];
                $payload['ad_platform'] = '磁力引擎';
                $payload['stat_date'] = date('Ymd', strtotime($detail['stat_date']));
                $payload['advertiser_id'] = (int)$detail['advertiser_id'];
                $payload['advertiser_name'] = $detail['advertiser_name'];
                $payload['campaign_id'] = (int)$detail['campaign_id'];
                $payload['campaign_name'] = $detail['campaign_name'];
                $payload['ad_id'] = (int)$detail['unit_id'];
                $payload['ad_name'] = $detail['unit_name'];
                $payload['creative_id'] = (int)$detail['creative_id'];
                $payload['creative_name'] = $detail['creative_name'] ?? '';
                $payload['show'] = (int)$detail['aclick'];
                $payload['click'] = (int)$detail['bclick'];
                $payload['cost'] = round($detail['charge'] * 100);
                $payload['active'] = (int)$detail['activation'];
                $payload['convert'] = (int)$detail['key_action'];
                $payloads[] = $payload;

            }
            ZengZhangCanMou::getInstance()->setAppCode($appcode)->sendDailyHourlyData($temporal_granularity, $payloads);
        }

    }
}
