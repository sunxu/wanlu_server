<?php
/**
 * 广告统计|N小时用户广告分布
 */

namespace App\Jobs;

use App\Services\Common\App;
use DB;

class AdUserJob extends BaseJob
{

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $level = DB::table('ad_level_ecpm')
            ->select([
                'level',
                'placement'
            ])
            ->where('app_code', $this->data['app_code'])
            ->get()
            ->toArray();
        $placementLevel = [];
        foreach ($level as $v) {
            if (!empty($v['placement'])) {
                $placementLevel[$v['level']][$v['placement']] = $v['placement'];
            }
        }
        if (empty($placementLevel)) {
            $this->fail($this->data['app_code'] . ' ecpm is empty.');
            return;
        }

        $placements = DB::table(App::tableEventAdShow($this->data['app_code']))
            ->where('android_id', $this->data['device_id'])
            ->whereBetween('create_time', [$this->data['start_time'], $this->data['end_time']])
//            ->where('event', 'ad_show')
            ->groupBy('placement')
            ->pluck(DB::raw("REPLACE(JSON_EXTRACT(info,'$.networkPlacementId'),'\"','') as placement"))
            ->toArray();
        // 过滤无代码位
        foreach ($placements as $k => $v) {
            if (empty($v)) {
                unset($placements[$k]);
            }
        }

        switch (true) {
//            case in_array(null, $placements, true):
//                $code = 99;
//                break;
            case empty($placements):
                $code = '0';
                break;
            case count(array_intersect($placements, $placementLevel[1])) == count($placements):
                $code = '1';
                break;
            case count(array_intersect($placements, array_merge($placementLevel[1], $placementLevel[2]))) == count($placements):
                $code = '1_2';
                break;
            case count(array_intersect($placements, array_merge($placementLevel[1], $placementLevel[3]))) == count($placements):
                $code = '1_3';
                break;
            default:
                $code = '99';
        }
        $result = DB::table('ad_level_user')
            ->updateOrInsert([
                'app_code' => $this->data['app_code'],
                'device_id' => $this->data['device_id']
            ], [
                'info' => json_encode(array_values($placements), 320),
                'level' => $code,
                'create_time' => time()
            ]);
        echo json_encode([
                'data' => $this->data,
                'result' => $result
            ], 320) . PHP_EOL;
    }
}
