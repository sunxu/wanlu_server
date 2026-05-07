<?php

declare (strict_types=1);
namespace App\ModelTools;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 代码位创建-代码位列表
 * @property string $create_key 创建的批次
 * @property int $account_id 账号ID
 * @property string $platform 平台 ks-快手，csj-穿山甲，ylh-优量汇，topon
 * @property string $app_id app_id
 * @property string $app_name app_name
 * @property string $placement_ad_type 广告类型
 * @property string $placement_name 代码位名称
 * @property string $placement_cpm 设置的cpm
 * @property string $set_cpm_result 设置cpm结果
 * @property string $placement_id 代码位ID
 * @property int $updated_time 更新时间
 * @property int $created_time 创建时间
 */
class PlacementList extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'placement_list';
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
    protected $casts = ['id' => 'integer', 'account_id' => 'integer', 'updated_time' => 'integer', 'created_time' => 'integer'];
}