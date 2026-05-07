<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Service\Placement\LaunchBaseService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class AdEcpm extends HyperfCommand
{
    protected $name = 'temp:adEcpm';

    protected $insured = [];
    protected $insured_init = [];

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:统计ecpm");
    }

    protected $appCode = 'tboxss';

    public function handle()
    {
        ini_set('memory_limit', '-1');
        $this->info('Begin:' . date("Y-m-d H:i:s"));
        $insured = Db::connection('tools')->table('ad_insured')->select(['placement_id', 'ecpm'])->get()->all();
        $in = [];
        foreach ($insured as $value){
            $in[data_get($value, 'placement_id')] = data_get($value,'ecpm');
        }
        $this->insured_init = object_to_arr($in);
        unset($in, $insured);

        $beginTime = strtotime('2022-09-03');
        for(;$beginTime <= strtotime('2022-09-07');){
            $this->initInsured($beginTime);

            $this->info(date('Y-m-d', $beginTime));
            $endTime = $beginTime + 60 * 60 * 24 - 1;
            $appDeviceModel = Db::connection('tools')->table("app_{$this->appCode}")
                ->whereBetween('create_time', [$beginTime, $endTime])->count();
            $this->info(date('Y-m-d', $beginTime) . 'tootal');
            $ecpmTotal = $this->ecpmDay($beginTime);
            var_dump($ecpmTotal);
            $this->info(date('Y-m-d', $beginTime) . 'inter');
            $ecpmInter = $this->ecpmDay($beginTime, 'inter');
            var_dump($ecpmInter);
            $this->info(date('Y-m-d', $beginTime) . 'splash');
            $ecpmsplash = $this->ecpmDay($beginTime, 'splash');
            var_dump($ecpmsplash);
            $this->info(date('Y-m-d', $beginTime) . 'full_video');
            $ecpmfull_video = $this->ecpmDay($beginTime, 'full_video');
            var_dump($ecpmfull_video);
            $this->info(date('Y-m-d', $beginTime) . 'native');
            $ecpmnative = $this->ecpmDay($beginTime, 'native');
            var_dump($ecpmnative);
            Db::connection('tools')->table('temp_0913')->insert([
                'created_date' => date('Y-m-d', $beginTime),
                'installed' => $appDeviceModel,
                'total_ecpm0' => $ecpmTotal['times0']<=0?$ecpmTotal['times0']: round($ecpmTotal['ecpm0'] / $ecpmTotal['times0'], 2),
                'total_ecpm1' => $ecpmTotal['times1']<=0?$ecpmTotal['times1']: round($ecpmTotal['ecpm1'] / $ecpmTotal['times1'], 2),
                'total_ecpm2' => $ecpmTotal['times2']<=0?$ecpmTotal['times2']: round($ecpmTotal['ecpm2'] / $ecpmTotal['times2'], 2),
                'total_ecpm3' => $ecpmTotal['times3']<=0?$ecpmTotal['times3']: round($ecpmTotal['ecpm3'] / $ecpmTotal['times3'], 2),
                'total_ecpm4' => $ecpmTotal['times4']<=0?$ecpmTotal['times4']: round($ecpmTotal['ecpm4'] / $ecpmTotal['times4'], 2),
                'total_ecpm5' => $ecpmTotal['times5']<=0?$ecpmTotal['times5']: round($ecpmTotal['ecpm5'] / $ecpmTotal['times5'], 2),
                'total_ecpm6' => $ecpmTotal['times6']<=0?$ecpmTotal['times6']: round($ecpmTotal['ecpm6'] / $ecpmTotal['times6'], 2),
                'total_ecpm7' => $ecpmTotal['times7']<=0?$ecpmTotal['times7']: round($ecpmTotal['ecpm7'] / $ecpmTotal['times7'], 2),

                'inter_ecpm0' => $ecpmInter['times0']<=0?$ecpmInter['times0']: round($ecpmInter['ecpm0'] / $ecpmInter['times0'], 2),
                'inter_ecpm1' => $ecpmInter['times1']<=0?$ecpmInter['times1']: round($ecpmInter['ecpm1'] / $ecpmInter['times1'], 2),
                'inter_ecpm2' => $ecpmInter['times2']<=0?$ecpmInter['times2']: round($ecpmInter['ecpm2'] / $ecpmInter['times2'], 2),
                'inter_ecpm3' => $ecpmInter['times3']<=0?$ecpmInter['times3']: round($ecpmInter['ecpm3'] / $ecpmInter['times3'], 2),
                'inter_ecpm4' => $ecpmInter['times4']<=0?$ecpmInter['times4']: round($ecpmInter['ecpm4'] / $ecpmInter['times4'], 2),
                'inter_ecpm5' => $ecpmInter['times5']<=0?$ecpmInter['times5']: round($ecpmInter['ecpm5'] / $ecpmInter['times5'], 2),
                'inter_ecpm6' => $ecpmInter['times6']<=0?$ecpmInter['times6']: round($ecpmInter['ecpm6'] / $ecpmInter['times6'], 2),
                'inter_ecpm7' => $ecpmInter['times7']<=0?$ecpmInter['times7']: round($ecpmInter['ecpm7'] / $ecpmInter['times7'], 2),

                'splash_ecpm0' => $ecpmsplash['times0']<=0?$ecpmsplash['times0']: round($ecpmsplash['ecpm0'] /$ecpmsplash['times0'], 2),
                'splash_ecpm1' => $ecpmsplash['times1']<=0?$ecpmsplash['times1']: round($ecpmsplash['ecpm1'] /$ecpmsplash['times1'], 2),
                'splash_ecpm2' => $ecpmsplash['times2']<=0?$ecpmsplash['times2']: round($ecpmsplash['ecpm2'] /$ecpmsplash['times2'], 2),
                'splash_ecpm3' => $ecpmsplash['times3']<=0?$ecpmsplash['times3']: round($ecpmsplash['ecpm3'] /$ecpmsplash['times3'], 2),
                'splash_ecpm4' => $ecpmsplash['times4']<=0?$ecpmsplash['times4']: round($ecpmsplash['ecpm4'] /$ecpmsplash['times4'], 2),
                'splash_ecpm5' => $ecpmsplash['times5']<=0?$ecpmsplash['times5']: round($ecpmsplash['ecpm5'] /$ecpmsplash['times5'], 2),
                'splash_ecpm6' => $ecpmsplash['times6']<=0?$ecpmsplash['times6']: round($ecpmsplash['ecpm6'] /$ecpmsplash['times6'], 2),
                'splash_ecpm7' => $ecpmsplash['times7']<=0?$ecpmsplash['times7']: round($ecpmsplash['ecpm7'] /$ecpmsplash['times7'], 2),

                'full_ecpm0' => $ecpmfull_video['times0']<=0?$ecpmfull_video['times0']: round($ecpmfull_video['ecpm0'] / $ecpmfull_video['times0'], 2),
                'full_ecpm1' => $ecpmfull_video['times1']<=0?$ecpmfull_video['times1']: round($ecpmfull_video['ecpm1'] / $ecpmfull_video['times1'], 2),
                'full_ecpm2' => $ecpmfull_video['times2']<=0?$ecpmfull_video['times2']: round($ecpmfull_video['ecpm2'] / $ecpmfull_video['times2'], 2),
                'full_ecpm3' => $ecpmfull_video['times3']<=0?$ecpmfull_video['times3']: round($ecpmfull_video['ecpm3'] / $ecpmfull_video['times3'], 2),
                'full_ecpm4' => $ecpmfull_video['times4']<=0?$ecpmfull_video['times4']: round($ecpmfull_video['ecpm4'] / $ecpmfull_video['times4'], 2),
                'full_ecpm5' => $ecpmfull_video['times5']<=0?$ecpmfull_video['times5']: round($ecpmfull_video['ecpm5'] / $ecpmfull_video['times5'], 2),
                'full_ecpm6' => $ecpmfull_video['times6']<=0?$ecpmfull_video['times6']: round($ecpmfull_video['ecpm6'] / $ecpmfull_video['times6'], 2),
                'full_ecpm7' => $ecpmfull_video['times7']<=0?$ecpmfull_video['times7']: round($ecpmfull_video['ecpm7'] / $ecpmfull_video['times7'], 2),

                'native_ecpm0' => $ecpmnative['times0']<=0?$ecpmnative['times0']: round($ecpmnative['ecpm0'] / $ecpmnative['times0'], 2),
                'native_ecpm1' => $ecpmnative['times1']<=0?$ecpmnative['times1']: round($ecpmnative['ecpm1'] / $ecpmnative['times1'], 2),
                'native_ecpm2' => $ecpmnative['times2']<=0?$ecpmnative['times2']: round($ecpmnative['ecpm2'] / $ecpmnative['times2'], 2),
                'native_ecpm3' => $ecpmnative['times3']<=0?$ecpmnative['times3']: round($ecpmnative['ecpm3'] / $ecpmnative['times3'], 2),
                'native_ecpm4' => $ecpmnative['times4']<=0?$ecpmnative['times4']: round($ecpmnative['ecpm4'] / $ecpmnative['times4'], 2),
                'native_ecpm5' => $ecpmnative['times5']<=0?$ecpmnative['times5']: round($ecpmnative['ecpm5'] / $ecpmnative['times5'], 2),
                'native_ecpm6' => $ecpmnative['times6']<=0?$ecpmnative['times6']: round($ecpmnative['ecpm6'] / $ecpmnative['times6'], 2),
                'native_ecpm7' => $ecpmnative['times7']<=0?$ecpmnative['times7']: round($ecpmnative['ecpm7'] / $ecpmnative['times7'], 2),
            ]);
            $beginTime += 60 * 60 * 24;
            sleep(1);
        }

        $this->info("End:" . date("Y-m-d H:i:s"));
    }

    public function initInsured($beginTime)
    {
        $ined = Db::connection('tools')->table('ad_report')->select(['placement_id', 'ecpm'])->where('date', date("Y-m-d", $beginTime))->get()->all();
        $this->insured = $this->insured_init;
        foreach ($ined as $value){
            if(data_get($value,'ecpm') <= 0){
                continue;
            }
            $this->insured[data_get($value, 'placement_id')] = data_get($value,'ecpm');
        }
        unset($ined);
    }

    public function ecpmDay($beginTime, $type = '')
    {
        $ecpm = [];
        for($i = 0; $i <= 7; $i++) {
            $ecpm["times{$i}"] = $this->times($beginTime, $type, $i);
            $ecpm["ecpm{$i}"] = $this->ecpm($beginTime, $type, $i);
        }

        return $ecpm;
    }

    public function ecpm($beginTime, $type = '', $day = 0)
    {
        $time = $beginTime + 60 * 60 * 24 * $day;
        $info = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show as aeas")
            ->leftJoin("app_{$this->appCode} as app", 'aeas.android_id', '=', 'app.android_id')
            ->whereBetween('app.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1])
            ->whereBetween('aeas.create_time', [$time, $time + 60 * 60 * 24 - 1])
            ->when(!empty($type), function ($query) use ($type) {
                $query->where('page', 'like', "%{$type}");
            })->select(['info', 'times'])->get()->all();
        $info_temp = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show_temp as aeas")
            ->leftJoin("app_{$this->appCode} as app", 'aeas.android_id', '=', 'app.android_id')
            ->whereBetween('app.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1])
            ->whereBetween('aeas.create_time', [$time, $time + 60 * 60 * 24 - 1])
            ->when(!empty($type), function ($query) use ($type) {
                $query->where('page', 'like', "%{$type}");
            })->select(['info', 'times'])->get()->all();
        return collect($info)->merge($info_temp)->sum(function($item){
            $info = json_decode(data_get($item, 'info'), true);
            $ecpm = $this->insured[data_get($info, 'networkPlacementId')]??0;
            return $ecpm * data_get($item, 'times');
        });
    }

    public function times($beginTime, $type = '', $day = 0)
    {
        $time = $beginTime + 60 * 60 * 24 * $day;
        $ecpmtimes = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show as aeas")
            ->leftJoin("app_{$this->appCode} as app", 'aeas.android_id', '=', 'app.android_id')
            ->whereBetween('app.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1])
            ->whereBetween('aeas.create_time', [$time, $time + 60 * 60 * 24 - 1])
            ->when(!empty($type), function ($query) use ($type) {
                $query->where('page', 'like', "%{$type}");
            })->sum('times');
        $ecpmtimes += Db::connection('tools')->table("app_{$this->appCode}_event_ad_show_temp as aeas")
            ->leftJoin("app_{$this->appCode} as app", 'aeas.android_id', '=', 'app.android_id')
            ->whereBetween('app.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1])
            ->whereBetween('aeas.create_time', [$time, $time + 60 * 60 * 24 - 1])
            ->when(!empty($type), function ($query) use ($type) {
                $query->where('page', 'like', "%{$type}");
            })->sum('times');
        return $ecpmtimes;
    }
}