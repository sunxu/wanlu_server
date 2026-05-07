<?php

declare(strict_types=1);

namespace App\Command\Temp;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class AdEcpmTimes extends HyperfCommand
{
    protected $name = 'temp:adecpmtimes';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:回本率");
        /**
         * 开屏-first这个广告覆盖的“新增用户量”占每日新增用户量的占比
         */
    }

    protected $appCodes = [
        'skqcl', 'qybh'
    ];

    protected $beginTime = '2023-02-14 00:00:00';
    protected $endTime = '2023-02-16 00:00:00';

    public function handle()
    {
        $this->info("开始" . date("Y-m-d H:i:s"));
        $beginTime = strtotime($this->beginTime);
        for (; $beginTime <= strtotime($this->endTime);) {
            foreach ($this->appCodes as $appCode) {
                $this->info('doing:' . $appCode . ':' . date("Y-m-d H:i:s", $beginTime));
                $androids = Db::connection('tools')->table("app_{$appCode}")
                    ->select(['android_id', 'oaid'])
                    ->where('create_time', '>=', $beginTime)
                    ->where('create_time', '<', $beginTime + 60 * 60 * 24)
                    ->get()->all();
                $androids = object_to_arr($androids);
                foreach ($androids as $value){
                    $adshowtable = $this->getadshowtable($beginTime, $appCode);
                    $adshowList = Db::connection('tools')->table($adshowtable)
                        ->select(['info'])
                        ->where('create_time', '>=', $beginTime)
                        ->where('create_time', '<', $beginTime + 60 * 60 * 24)
                        ->where('android_id', data_get($value, 'android_id'))
                        ->orderBy('create_time')
                        ->get()->all();
                    $adshowList = object_to_arr($adshowList);
                    $insertData = [];
                    $adTimes = 1;
                    foreach ($adshowList as $ad) {
                        $adinfo = data_get($ad, 'info');
                        $adinfo = json_decode($adinfo, true);
                        $insertData[] = [
                            'date' => date("Y-m-d", $beginTime),
                            'appcode' => $appCode,
                            'oaid' => data_get($value, 'oaid'),
                            'android_id' => data_get($value, 'android_id'),
                            'times' => $adTimes,
                            'ecpm' =>data_get($adinfo, 'realEcpm'),
                            'ad_type' =>data_get($adinfo, 'adType'),
                        ];
                        $adTimes++;
                    }
                    unset($adshowList);
                    Db::connection('tools')->table('temp_adtimes_20230217')->insert($insertData);
                }
            }
            $beginTime += 60 * 60 * 24;
        }
        $this->info("结束" . date("Y-m-d H:i:s"));
    }

    public function getadshowtable($time, $appCode)
    {
        if ($time < strtotime(date("Y-m-d 00:00:00", time() - 60 * 60 * 24 * 2))) {
            return "app_{$appCode}_event_ad_show_temp";
        }
        return "app_{$appCode}_event_ad_show";
    }
}