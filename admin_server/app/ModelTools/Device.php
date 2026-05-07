<?php

declare (strict_types=1);

namespace App\ModelTools;

use App\Utils\SuffixModel;

/**
 * @property int $id
 * @property int $channel 渠道
 * @property string $imei IMEI
 * @property string $oaid OAID
 * @property string $android_id Android ID
 * @property string $vendor 生产厂商
 * @property string $model 设备型号
 * @property string $mac MAC地址
 * @property string $ip IP地址
 * @property string $location 定位信息
 * @property string $wifi_list WiFi列表
 * @property string $app_list 应用列表
 * @property string $net 网络名称
 * @property string $os_version 系统版本
 * @property string $version APP版本
 * @property int $source [1]巨量[2]快手
 * @property int $ad_id 点击检测ID
 * @property int $ad_time 归因时间
 * @property int $active_time 激活时间
 * @property int $keep_time 次留时间
 * @property int $action_time 关键行为时间
 * @property string $action_info 关键行为信息
 * @property string $account_id 广告主ID
 * @property string $aid 广告计划ID
 * @property int $update_time 更新时间
 * @property int $create_time 创建时间
 */
class Device extends SuffixModel
{
    public $timestamps = false;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'app_{suffix}';
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'tools';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'channel' => 'integer', 'source' => 'integer', 'ad_id' => 'integer', 'ad_time' => 'integer', 'active_time' => 'integer', 'keep_time' => 'integer', 'action_time' => 'integer', 'update_time' => 'integer', 'create_time' => 'integer', 'location' => 'array'];
}