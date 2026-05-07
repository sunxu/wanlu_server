<?php
/**
 * 关键行为
 */

namespace App\Jobs;

use App\Services\Common\Account;
use App\Services\Common\App;
use Cache;
use DB;

class DeviceActionJob extends BaseJob
{
    /**
     * 失败执行次数|每小时一次
     * @var int
     */
    public $tries = 48;

    /**
     * 频次|分钟
     * @var int
     */
    public $minutes = 30;

    /***
     * 关键行为指标类型
     * @var array
     */
    public $actionType = ['roi', 'ecpm', 'arpu'];

    public $operator = ['or', 'and'];

    public $actionInfo = [];

    public $confExampel = [
        'op' => 'or',
        'ecpm' => [
            'ad_type' => ['full_video', 'inter_full'],
            'num' => 1,
            'value' => 30
        ],
        'roi' => [
            'value' => 70
        ],
        'arpu' => [
            'ad_type' => ['full_video', 'inter_full'],
            'value' => 1
        ]
    ];

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 关键行为时间
        $actionTime = DB::table(App::tableDevice($this->data['app_code']))
            ->where('android_id', '=', $this->data['device_id'])
            ->value('action_time');
        if ($actionTime > 0) {
            return;
        }

        $device = DB::table(App::tableDevice($this->data['app_code']))
            ->where('android_id', $this->data['device_id'])
            ->first([
                'vendor',
                'os_version',
            ]);
        $vendor = strtolower($device['vendor'] ?? '');
        $allowedVersions = ['10.0', '12.0', '13.0'];

        if ($vendor === 'vivo' && !in_array($device['os_version'], $allowedVersions)) {
            return;
        }

        // 关键行为条件
        $actionValue = Account::actionValue($this->data['account_id']);

        if ($actionValue['action_type'] == 2) {
            return $this->novel();
        }

        // 非自定义
        if ($actionValue['action_type'] != 3) {
            return;
        }

        // 多种组合配置
        $confArr = $actionValue['action_value'];

        foreach ($confArr as $confKey => $confValue) {

            // 运算符
            $operator = strtolower($confValue['op'] ?? 'and');

            if (!in_array($operator, $this->operator) || empty($confValue) || !is_array($confValue)) {
                continue;
            }

            $action = [];
            foreach ($confValue as $k => $v) {
                unset($target);
                $k = strtolower($k);
                if (!in_array($k, $this->actionType) || !is_array($v)) {
                    continue;
                }

                if ($operator == 'or') {
                    $target = ($target ?? false) || $action[$k] = call_user_func([$this, $k], $v);
                    if ($target) {
                        break;
                    }
                }

                if ($operator == 'and') {
                    $target = ($target ?? true) && $action[$k] = call_user_func([$this, $k], $v);
                    if (!$target) {
                        break;
                    }
                }
            }

            $this->actionInfo[$confKey] = $action;

            if ($target ?? false) {
                foreach ($this->actionInfo as $k => $v) {
                    if (empty($v)) {
                        continue;
                    }
                    $t = true;
                    foreach ($v as $v2) {
                        $t = $t && $v2;
                    }
                    if ($t) {
                        $this->actionInfo['target'] = $k;
                    }
                }
                return $this->action();
            }
        }

        DB::table(App::tableDevice($this->data['app_code']))
            ->where('android_id', '=', $this->data['device_id'])
            ->update([
                'action_info' => json_encode($this->actionInfo),
                'update_time' => time()
            ]);
        if ($this->attempts() < $this->tries && time() < $this->data['end_time']) {
            // redis 队列迁移支持集群
            strstr($this->queue, '{') ? $this->release($this->minutes * 60) : dispatch((new DeviceActionJob($this->data))
                ->onQueue('{' . $this->queue . '}')->delay($this->minutes * 60));
//            $this->release($this->minutes * 60);
        }
    }

    /**
     * 设备回收
     */
    public function deviceRevenue()
    {
//        if (in_array($this->data['app_code'], config('ad.test_app', []))) {
            return $this->revenue();
//        }
        $key = 'arpu:' . $this->data['app_code'] . ':' . md5(json_encode($this->data));
        $data = Cache::get($key);
        if (is_null($data)) {
            $data = DB::table(App::tableIntervalGroup($this->data['app_code']))
                ->where('device_id', $this->data['device_id'])
                // 收入10分钟分段处理，前后各加10分钟避免遗漏统计
                ->where('start_time', '>=', $this->data['start_time'] - 600)
                ->where('end_time', '<=', $this->data['end_time'] + 600)
                ->value(DB::raw("SUM(amount)"));
            $data = round($data, 4);
            Cache::put($key, $data, 60);
        }
        return $data;
    }

    public function revenue()
    {
        $key = 'arpu:' . $this->data['app_code'] . ':' . md5(json_encode($this->data));
        $data = Cache::get($key);
        if (is_null($data)) {
            $data = DB::table(App::tableEventAdRevenue($this->data['app_code']))
                ->where('device_id', $this->data['device_id'])
                ->where('time', '>=', date('YmdHis', $this->data['start_time']))
                ->where('time', '<', date('YmdHis', $this->data['end_time']))
                ->value(DB::raw("SUM(amount)"));
            $data = round($data, 4);
            Cache::put($key, $data, 60);
        }
        return $data;
    }

    /**
     * 广告ECPM
     * @param array $conf = [
     * 'ad_type' => ['full_video', 'inter_full'],
     * 'num' => 1,
     * 'value' => 30
     * ]
     * @return bool
     */
    public function ecpm(array $conf = [])
    {
        if (empty($conf['ad_type']) || !is_array($conf['ad_type'])
            || empty($conf['num']) || $conf['num'] <= 0
            || !isset($conf['value']) || $conf['value'] < 0) {
            return false;
        }

        $placements = $this->getPlacement($conf['ad_type']);
        // 不限定ECPM值
        if ($conf['value'] == 0) {
            return array_sum(array_column($placements, 'num')) >= $conf['num'];
        }

        $times = 0;

        foreach ($placements as $item) {
            if (($item['ecpm'] ?? 0) >= $conf['value']) {
                $times += $item['num'];
                if ($times >= $conf['num']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 回收ROI
     * @param array $conf = [
     * 'value' => 30
     * ]
     * @return bool
     */
    public function roi(array $conf = [])
    {
        if (empty($conf['value']) || $conf['value'] < 0) {
            return false;
        }
        // 设备成本|激活日
        $cost = Account::activeCost(date('Y-m-d', $this->data['start_time']), $this->data['plan_id']);
        // 回收
        $revenue = $this->revenue();

        return $cost > 0 && round($revenue * 100 / $cost, 2) >= $conf['value'];
    }

    /**
     * 用户回收
     * @param array $conf = [
     * 'ad_type' => ['full_video', 'inter_full'],
     * 'value' => 1
     * ]
     * @return bool
     */
    public function arpu(array $conf = [])
    {
        if (empty($conf['value']) || $conf['value'] < 0) {
            return false;
        }

        // 不区分广告类型的收入
        if (empty($conf['ad_type'])) {
            return $this->revenue() >= $conf['value'];
        }

        $placements = $this->getPlacement($conf['ad_type']);
        $arpu = 0;
        foreach ($placements as $item) {
            $arpu += ($item['ecpm'] ?? 0) * $item['num'];
            if ($arpu >= ($conf['value'] * 1000)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 广告信息（类型、次数、ecpm）
     * @param array $adType
     * @return array
     */
    private function getPlacement($adType = [])
    {
        $key = 'placement:' . $this->data['app_code'] . ':' . md5(json_encode($this->data) . json_encode($adType));
        $data = Cache::get($key);

        if (is_null($data)) {
            $data = DB::table(App::tableEventAdShow($this->data['app_code']))
                ->where(function ($sql) use ($adType) {
                    foreach ($adType as $type) {
                        $sql->orWhere('page', 'like', "%{$type}%");
                    }
                })
                ->where('android_id', $this->data['device_id'])
                ->whereBetween('create_time', [$this->data['start_time'], $this->data['end_time']])
                ->select([
                    DB::raw('COUNT(1) as num'),
                    DB::raw("info->>'$.networkPlacementId' as placement"),
                    DB::raw("info->>'$.realEcpm' as ecpm")
                ])
                ->groupBy(['placement', 'ecpm'])
                ->get()
                ->toArray();
            Cache::put($key, $data, 60);
        }
        return $data;
    }

    /**
     * 关键行为达成
     */
    private function action()
    {
        $currTime = time();
        // 满足关键行为
        $tableAd = App::tableAdBySource($this->data['app_code'], $this->data['source']);
        DB::table($tableAd)
            ->where('id', '=', $this->data['ad_id'])
            ->update([
                'action_status' => 1,
                'action_time' => $currTime,
                'update_time' => $currTime
            ]);
        DB::table(App::tableDevice($this->data['app_code']))
            ->where('android_id', '=', $this->data['device_id'])
            ->where('action_time', '=', 0)
            ->update([
                'action_time' => $currTime,
                'action_info' => json_encode($this->actionInfo),
                'update_time' => $currTime
            ]);
        dispatch((new NotifyAdActionJob([
            'app_code' => $this->data['app_code'],
            'device_id' => $this->data['device_id'],
            'account_id' => $this->data['account_id'],
            'source' => $this->data['source'],
            'ad_id' => $this->data['ad_id'],
            'action_time' => $currTime,
            'active_time' => $this->data['start_time'],
        ]))->onQueue('{notify_ad_action}'));
    }

    /**
     * 小说加桌判定
     */
    private function novel()
    {
        $id = DB::table(App::tableEventDialog($this->data['app_code']))
            ->where('android_id', $this->data['device_id'])
            ->where('key_type', 'desktop')
            ->where('req_type', 'yes')
            ->value('id');
        if ($id) {
            return $this->action();
        }

        if ($this->attempts() < $this->tries && time() < $this->data['end_time']) {
            $this->release($this->minutes * 60);
        }
        return;
    }
}
