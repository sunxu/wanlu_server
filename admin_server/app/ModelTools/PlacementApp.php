<?php

declare (strict_types=1);
namespace App\ModelTools;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 代码位创建-应用
 * @property int $account_id 所属账号
 * @property string $app_id 应用ID
 * @property string $app_name 应用名称
 * @property string $package_name 包名
 * @property int $status 应用状态 1-正常 0-停用
 * @property int $created_time 创建时间
 * @property int $updated_time 更新时间
 */
class PlacementApp extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'placement_app';
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
    protected $casts = ['id' => 'integer', 'account_id' => 'integer', 'status' => 'integer', 'created_time' => 'integer', 'updated_time' => 'integer'];
}