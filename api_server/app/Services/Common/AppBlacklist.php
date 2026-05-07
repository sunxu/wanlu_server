<?php

declare(strict_types=1);

namespace App\Services\Common;

use App\Services\Helper\Tools\LogPush;
use DB;
use Exception;
use Illuminate\Support\Facades\Redis;

class AppBlacklist
{
    /**
     * @var mixed
     */
    protected $redis;

    /**
     * @var
     */
    protected $prefix;

    /**
     * @var array
     */
    public $blackFields = [
        'imei',
        'oaid',
        'android_id',
        'ip'
    ];

    public function __construct()
    {
        $this->redis = Redis::connection()->client();
    }

    /**
     * 初始化黑名单集合
     */
    public function init()
    {
        $list = DB::table('blacklist')
            ->select($this->blackFields)
            ->get()
            ->toArray();

        foreach ($this->blackFields as $field) {
            $values = array_filter(array_column($list, $field));
            if (!empty($values)) {
                $this->add($field, $values);
            }
        }
    }

    /**
     * 添加黑名单
     * @param string $type
     * @param array $values
     */
    public function add(string $type, array $values)
    {
        foreach ($values as $value) {
            $this->redis->zAdd($this->getKey($type), ['NX'], 0, $value);
        }
    }

    /**
     * 是否在黑名单并
     * @param string $type
     * @param string $value
     * @return bool
     */
    public function isBlack(string $type, string $value)
    {
        $type = $this->getKey($type);

        if ($this->redis->zScore($type, $value) !== false) {
            $this->redis->zIncrBy($type, 1, $value);
            return true;
        }

        return false;
    }

    /**
     * 是否存在黑名单
     * @param string $type
     * @param string $value
     * @return bool
     */
    public function isExists(string $type, string $value)
    {
        $type = $this->getKey($type);

        return $this->redis->zScore($type, $value) !== false;
    }

    /**
     * 添加至黑名单集合
     * @param array $deviceInfo
     */
    public function addDevice(array $deviceInfo)
    {
        foreach ($this->blackFields as $field) {
            if (!empty($deviceInfo[$field])) {
                $this->insertBlack($deviceInfo);
                $this->add($field, [$deviceInfo[$field]]);
            }
        }
    }

    /**
     * @param $deviceInfo
     */
    private function insertBlack($deviceInfo)
    {
        if ($this->isExists('android_id', $deviceInfo['android_id'])) {
            return;
        }

        $deviceInfo['create_time'] = time();

        try {
            DB::table('blacklist')
                ->insertOrIgnore($deviceInfo);
        } catch (Exception $e) {
            LogPush::error(__CLASS__ . ':' . __FUNCTION__, ['exception' => $e->getMessage()]);
        }
    }

    private function getKey($key)
    {
        return sprintf('%s:blacklist:%s', env('APP_NAME'), $key);
    }

}