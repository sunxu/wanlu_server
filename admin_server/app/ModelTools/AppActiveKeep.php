<?php

declare (strict_types=1);
namespace App\ModelTools;

use App\Utils\SuffixModel;
/**
 * @property int $id 活跃留存设备表
 * @property string $device_id 设备ID
 * @property int $active_day 活跃日期
 * @property int $install_day 安装日期
 * @property int $install_keep_num 安装留存天数
 * @property int $activate_day 激活日期
 * @property int $activate_keep_num 激活留存天数
 * @property int $is_ad_report 是否有广告上报
 * @property int $times 请求次数
 * @property int $created_time 创建时间
 */
class AppActiveKeep extends SuffixModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'app_{suffix}_active_keep';
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
    protected $casts = ['id' => 'integer', 'active_day' => 'integer', 'install_day' => 'integer', 'install_keep_num' => 'integer', 'activate_day' => 'integer', 'activate_keep_num' => 'integer', 'is_ad_report' => 'integer', 'times' => 'integer', 'created_time' => 'integer'];
}