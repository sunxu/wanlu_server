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
class AdEcpmVender extends HyperfCommand
{
    protected $name = 'temp:adEcpmvender';

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

        $beginTime = strtotime('2022-09-12');
        for(;$beginTime <= strtotime('2022-09-14');){
            $this->initInsured($beginTime);

            $this->info(date('Y-m-d', $beginTime));
            Db::connection('tools')->table('temp_0915')->insert([
                'created_date' => date('Y-m-d', $beginTime),

                'total_install' => $this->installed($beginTime),
                'xiaomi_install' => $this->installed($beginTime, 'Xiaomi'),
                'huawei_install' => $this->installed($beginTime, 'HUAWEI'),
                'vivo_install' => $this->installed($beginTime, 'vivo'),
                'oppo_install' => $this->installed($beginTime, 'OPPO'),

                'total_active' => $this->actived($beginTime),
                'xiaomi_active' => $this->actived($beginTime, 'Xiaomi'),
                'huawei_active' => $this->actived($beginTime, 'HUAWEI'),
                'vivo_active' => $this->actived($beginTime, 'vivo'),
                'oppo_active' => $this->actived($beginTime, 'OPPO'),

                'total_install_ecpm' => $this->ecpmInstalled($beginTime),
                'xiaomi_install_ecpm' => $this->ecpmInstalled($beginTime, 'Xiaomi'),
                'huawei_install_ecpm' => $this->ecpmInstalled($beginTime, 'HUAWEI'),
                'vivo_install_ecpm' => $this->ecpmInstalled($beginTime, 'vivo'),
                'oppo_install_ecpm' => $this->ecpmInstalled($beginTime, 'OPPO'),

                'total_active_ecpm' => $this->ecpmActived($beginTime),
                'xiaomi_active_ecpm' => $this->ecpmActived($beginTime, 'Xiaomi'),
                'huawei_active_ecpm' => $this->ecpmActived($beginTime, 'HUAWEI'),
                'vivo_active_ecpm' => $this->ecpmActived($beginTime, 'vivo'),
                'oppo_active_ecpm' => $this->ecpmActived($beginTime, 'OPPO'),
            ]);
            $beginTime += 60 * 60 * 24;
            sleep(1);
        }

        $this->info("End:" . date("Y-m-d H:i:s"));
    }

    public function installed($beginTime, $vendor = '')
    {
        return Db::connection('tools')->table("app_{$this->appCode}")
            ->whereBetween('create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1])
            ->when(!empty($vendor), function ($query) use ($vendor) {
                $query->where('vendor', $vendor);
            })
            ->count();
    }

    public function actived($beginTime, $vendor = '')
    {
        $num = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show as aeas")
            ->whereBetween('aeas.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1])
            ->when(!empty($vendor), function ($query) use ($vendor) {
                $query->where('vendor', $vendor);
            })->count(Db::raw("distinct android_id"));
        $num += Db::connection('tools')->table("app_{$this->appCode}_event_ad_show_temp as aeas")
            ->whereBetween('aeas.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1])
            ->when(!empty($vendor), function ($query) use ($vendor) {
                $query->where('vendor', $vendor);
            })->count(Db::raw("distinct android_id"));
        return $num;
    }

    public function ecpmActived($beginTime, $vendor = '')
    {
        $ecpm = $this->ecpm($beginTime, 'active', $vendor);
        $times = $this->times($beginTime, 'active', $vendor);
        if($times <= 0){
            return 0;
        }
        return round($ecpm/$times, 2);
    }

    public function ecpmInstalled($beginTime, $vendor = '')
    {
        $ecpm = $this->ecpm($beginTime, 'install', $vendor);
        $times = $this->times($beginTime, 'install', $vendor);
        if($times <= 0){
            return 0;
        }
        return round($ecpm/$times, 2);
    }

    public function times($beginTime, $type, $vendor)
    {
        $ecpmtimes = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show as aeas")
            ->leftJoin("app_{$this->appCode} as app", 'aeas.android_id', '=', 'app.android_id')
            ->when($type == 'install', function($query) use($beginTime) {
                $query->whereBetween('app.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1]);
            })
            ->whereBetween('aeas.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1])
            ->when(!empty($vendor), function ($query) use ($vendor) {
                $query->where('aeas.vendor', $vendor);
            })->sum('times');
        $ecpmtimes += Db::connection('tools')->table("app_{$this->appCode}_event_ad_show_temp as aeas")
            ->leftJoin("app_{$this->appCode} as app", 'aeas.android_id', '=', 'app.android_id')
            ->when($type == 'install', function($query) use($beginTime) {
                $query->whereBetween('app.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1]);
            })
            ->whereBetween('aeas.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1])
            ->when(!empty($vendor), function ($query) use ($vendor) {
                $query->where('aeas.vendor', $vendor);
            })->sum('times');
        return $ecpmtimes;
    }

    public function ecpm($beginTime, $type, $vendor)
    {
        $info = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show as aeas")
            ->leftJoin("app_{$this->appCode} as app", 'aeas.android_id', '=', 'app.android_id')
            ->when($type == 'install', function($query) use($beginTime) {
                $query->whereBetween('app.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1]);
            })
            ->whereBetween('aeas.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1])
            ->when(!empty($vendor), function ($query) use ($vendor) {
                $query->where('aeas.vendor', $vendor);
            })->select(['info', 'times'])->get()->all();

        $info_temp = Db::connection('tools')->table("app_{$this->appCode}_event_ad_show_temp as aeas")
            ->leftJoin("app_{$this->appCode} as app", 'aeas.android_id', '=', 'app.android_id')
            ->when($type == 'install', function($query) use($beginTime) {
                $query->whereBetween('app.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1]);
            })
            ->whereBetween('aeas.create_time', [$beginTime, $beginTime + 60 * 60 * 24 - 1])
            ->when(!empty($vendor), function ($query) use ($vendor) {
                $query->where('aeas.vendor', $vendor);
            })->select(['info', 'times'])->get()->all();

        return collect($info)->merge($info_temp)->sum(function($item){
            $info = json_decode(data_get($item, 'info'), true);
            $ecpm = $this->insured[data_get($info, 'networkPlacementId')]??0;
            return $ecpm * data_get($item, 'times');
        });
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
}