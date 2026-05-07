<?php

declare (strict_types=1);

namespace App\Model\Media;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property string $app_code 应用标识
 * @property string $date 日期
 * @property string $media_type 媒体类型
 * @property int $media_company 媒体主体ID
 * @property string $app_id 媒体应用ID
 * @property string $app_name 媒体应用名
 * @property string $placement_id 代码位ID
 * @property int $placement_type 代码位类型
 * @property string $placement_name 代码位名
 * @property string $revenue 回收金额
 * @property string $ecpm 千次人均展示单价
 * @property int $request 请求数
 * @property int $fill 填充数
 * @property int $show 展示数
 * @property int $click 点击数
 * @property int $created_time 创建时间
 * @property int $updated_time 更新时间
 */
class MediaIncome extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    protected $dateFormat = 'U';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'media_income';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date', 'media_type', 'app_id', 'app_name', 'placement_id', 'media_company',
        'placement_name', 'placement_type', 'revenue', 'ecpm', 'request',
        'fill', 'show', 'click', 'app_code'
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'request' => 'integer', 'fill' => 'integer', 'show' => 'integer', 'click' => 'integer', 'created_time' => 'integer', 'updated_time' => 'integer'];
}