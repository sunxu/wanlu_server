<?php

declare (strict_types=1);
namespace App\ModelTools;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 代码位创建-平台账号表
 * @property string $platform 平台 ks-快手，csj-穿山甲，ylh-优量汇，topon
 * @property string $platform_name 平台名称
 * @property string $subject 账号主体
 * @property string $conf 账号配置
 * @property int $status 状态 1-正常 0-停用
 * @property int $created_time 创建时间
 */
class PlacementAccount extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'placement_account';
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
    protected $casts = ['id' => 'integer', 'status' => 'integer', 'created_time' => 'integer'];
}