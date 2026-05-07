<?php

namespace App\Jobs;

use App\Services\Common\Account;
use App\Services\Common\App;
use Cache;
use DB;

class DeviceActiveCustomJob extends BaseJob
{
    public $tries = 10;

    public $minutes = 30;

    protected $maxHour = 6;

    protected $triesTimes = [
        1 => 2 * 60,
        2 => 5 * 60,
        3 => 10 * 60,
        4 => 20 * 60,
        5 => 30 * 60,
        6 => 60 * 60,
        7 => 60 * 60
    ];

    public $actionType = ['roi', 'ecpm', 'arpu', 'ipu'];

    public $operator = ['or', 'and'];

    public $actionInfo = [];

    protected $device = [];

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

    public function log($line, ...$args)
    {
        $log_file = '/tmp/custom_job_log.txt';
        $time = date("Y-m-d H:i:s");
        $message = json_encode($args, JSON_UNESCAPED_UNICODE);
        $log_message = "$time $line $message \n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->log(__LINE__, "handle job", $this->data);

        $device = DB::table(App::tableDevice($this->data['app_code']))
            ->where('android_id', $this->data['device_id'])
            ->first([
                'id',
                'ad_time',
                'ad_id',
                'account_id',
                'aid as plan_id',
                'source',
                'active_time',
                'vendor',
                'os_version',
            ]);
        if (empty($device['ad_id']) || empty($device['source']) || empty($device['account_id'])) {
            echo 'empty ad_id' . PHP_EOL;
            return;
        }
        if (!empty($device['active_time'])) {
            echo 'active_time > 0' . PHP_EOL;
            return;
        }

        $vendor = strtolower($device['vendor']?? '');
        $allowedVersions = ['10.0', '12.0', '13.0'];

        if ($vendor === 'vivo' && !in_array($device['os_version'], $allowedVersions)) {
            $this->log(__LINE__, "ignore vivo version", $device);
            return;
        }

        $device['start_time'] = $device['ad_time'];
        $device['end_time'] = $device['ad_time'] + ($this->maxHour * 3600);
        $this->device = $device;
        $activeValue = Account::activeValue($device['account_id']);

        if ($activeValue['active_type'] != 4) {
            return;
        }

        // 多种组合配置
        $confArr = $activeValue['active_value'];
        $this->log(__LINE__, "active value conf", $this->data);
        foreach ($confArr as $confKey => $confValue) {
            $this->log(__LINE__, "handle config", $confKey, $confValue);

            // 运算符
            $operator = strtolower($confValue['op'] ?? 'and');

            if (!in_array($operator, $this->operator) || empty($confValue) || !is_array($confValue)) {
                $this->log(__LINE__, "pass this operator", $operator);
                continue;
            }

            $action = [];
            foreach ($confValue as $k => $v) {
                unset($target);
                $k = strtolower($k);
                if (!in_array($k, $this->actionType) || !is_array($v)) {
                    $this->log(__LINE__, "pass this action type", $operator, $k, $v);
                    continue;
                }

                if ($operator == 'or') {
                    $target = ($target ?? false) || $action[$k] = call_user_func([$this, $k], $v);
                    if ($target) {
                        $this->log(__LINE__, "get target", $operator, $k, $v);
                        break;
                    }
                }

                if ($operator == 'and') {
                    $target = ($target ?? true) && $action[$k] = call_user_func([$this, $k], $v);
                    if (!$target) {
                        $this->log(__LINE__, "get target", $operator, $k, $v);
                        break;
                    }
                }
            }

            $this->actionInfo[$confKey] = $action;
            $this->log(__LINE__, "result", $target, $action);

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
                $this->log(__LINE__, "run action, return");
                $this->log(__LINE__, "actionInfo", $this->actionInfo);
                return $this->action();
            }
        }
        $this->log(__LINE__, "actionInfo", $this->actionInfo);

        DB::table(App::tableDevice($this->data['app_code']))
            ->where('android_id', '=', $this->data['device_id'])
            ->update([
                'net' => json_encode($this->actionInfo),
                'update_time' => time()
            ]);

        if ($this->attempts() < $this->tries && time() < $this->device['end_time']) {
            $this->release($this->triesTimes[$this->attempts()] ?? 3600);
        }
    }

    protected function revenue()
    {
        $key = 'arpu:' . $this->data['app_code'] . ':' . md5(json_encode($this->data));
        $data = Cache::get($key);
        if (is_null($data)) {
            $data = DB::table(App::tableEventAdRevenue($this->data['app_code']))
                ->where('device_id', $this->data['device_id'])
                ->where('time', '>=', date('YmdHis', $this->device['start_time']))
                ->where('time', '<', date('YmdHis', $this->device['end_time']))
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
    protected function ecpm(array $conf = [])
    {
        if (empty($conf['num']) || $conf['num'] <= 0 || !isset($conf['value']) || $conf['value'] < 0) {
            return false;
        }

        $placements = $this->getPlacement($conf['ad_type'] ?? []);
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
    protected function roi(array $conf = [])
    {
        if (empty($conf['value']) || $conf['value'] < 0) {
            return false;
        }
        // 设备成本|激活日
        $cost = Account::activeCost(date('Y-m-d', $this->device['start_time']), $this->device['plan_id']);
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
    protected function arpu(array $conf = [])
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

    protected function ipu(array $conf = [])
    {
        if (empty($conf['value']) || $conf['value'] < 0) {
            return false;
        }
        $placements = $this->getPlacement($conf['ad_type'] ?? []);
        return array_sum(array_column($placements, 'num')) >= $conf['value'];
    }

    /**
     * 广告信息（类型、次数、ecpm）
     * @param array $adType
     * @return array
     */
    protected function getPlacement($adType = [])
    {
        $key = 'placement:' . $this->data['app_code'] . ':' . md5(json_encode($this->data) . json_encode($adType));
        $data = Cache::get($key);

        if (is_null($data)) {
            $data = DB::table(App::tableEventAdShow($this->data['app_code']))
                ->when(!empty($adType), function ($sql) use ($adType) {
                    $sql->whereIn('info->adType', $adType);
                })
                ->where('android_id', $this->data['device_id'])
                ->whereBetween('create_time', [$this->device['start_time'], $this->device['end_time']])
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
    protected function action()
    {
        $currTime = time();
        DB::table(App::tableDevice($this->data['app_code']))
            ->where('android_id', '=', $this->data['device_id'])
            ->update([
                'net' => json_encode($this->actionInfo),
                'update_time' => $currTime
            ]);
        dispatch((new DeviceActiveJob([
            'app_code' => $this->data['app_code'],
            'device_id' => $this->data['device_id'],
            'active_time' => $currTime,
        ]))->onQueue('{device_active}'));
    }
}
