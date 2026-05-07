<?php

declare (strict_types=1);
namespace App\ModelTools;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 
 * @property string $app_code 应用标识
 * @property int $media_type 广告平台1(穿山甲)2(腾讯)3(快手)
 * @property int $source 投放媒体1(巨量)2(快手)
 * @property int $lanuch_type 投放方式1关键行为2双出
 * @property string $ad_scene 广告场景
 * @property string $placement_type 代码位类型
 * @property string $placement_name 代码位名称
 * @property string $topon_id 广告位ID
 * @property string $placement_id 代码位ID
 * @property string $ecpm 保价ecpm
 * @property int $level 层级
 * @property int $fall_level 瀑布流层级
 * @property int $is_deleted 
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 * @property int $delete_time 删除时间
 */
class AdInsured extends Model
{
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    public $timestamps = true;
    protected $dateFormat = 'U';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ad_insured';
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
    protected $fillable = [
        'app_code',
        'media_type',
        'source',
        'lanuch_type',
        'ad_scene',
        'placement_type',
        'placement_name',
        'topon_id',
        'placement_id',
        'ecpm',
        'level',
        'fall_level'
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'media_type' => 'integer', 'source' => 'integer', 'lanuch_type' => 'integer', 'level' => 'integer', 'fall_level' => 'integer', 'is_deleted' => 'integer', 'create_time' => 'integer', 'update_time' => 'integer', 'delete_time' => 'integer'];
}