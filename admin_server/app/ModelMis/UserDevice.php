<?php

declare (strict_types=1);
namespace App\ModelMis;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $user_id 用户id
 * @property string $device_id 设备id
 * @property string $imei IMEI
 * @property string $oaid OAID
 * @property string $vendor_type 生产厂商
 * @property string $model 设备型号
 * @property string $mac MAC地址
 * @property string $ip IP地址
 * @property int $is_except 是否异常：1=正常，0=异常
 * @property string $os_version 系统版本
 * @property string $device_os 设备系统
 * @property int $created_time 创建时间
 * @property int $updated_time 修改时间
 * @property string $did did
 * @property string $unionid unionid
 * @property string $did_response 
 */
class UserDevice extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_device';
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mis';
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
    protected $casts = ['user_id' => 'integer', 'is_except' => 'integer', 'created_time' => 'integer', 'updated_time' => 'integer'];
}