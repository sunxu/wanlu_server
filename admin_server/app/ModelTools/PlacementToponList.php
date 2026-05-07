<?php

declare (strict_types=1);
namespace App\ModelTools;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 代码位创建-代码位列表
 * @property string $create_key 创建的批次
 * @property int $account_id 账号ID
 * @property string $app_id app_id
 * @property string $app_name app_name
 * @property string $placement_ad_type 广告类型
 * @property string $placement_name 代码位名称
 * @property string $placement_id 代码位ID
 * @property string $topon_id ToponID
 * @property string $topon_cpm Topon价格
 * @property int $updated_time 更新时间
 * @property int $created_time 创建时间
 */
class PlacementToponList extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'placement_topon_list';
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