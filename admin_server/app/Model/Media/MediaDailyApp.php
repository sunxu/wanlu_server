<?php

declare (strict_types=1);

namespace App\Model\Media;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property string $date 日期
 * @property string $media_type 已更新媒体
 * @property string $total_cost 消耗
 * @property string $media_revenue 媒体收入
 * @property string $total_revenue 毛利收入
 * @property string $detail 收入明细
 * @property string $other_revenue 余量收入
 * @property int $created_time 创建时间
 * @property int $updated_time 修改时间
 */
class MediaDailyApp extends Model
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
    protected $table = 'media_daily_app';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['date', 'media_type', 'total_cost', 'media_revenue', 'total_revenue', 'detail', 'other_revenue'];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'created_time' => 'integer', 'updated_time' => 'integer', 'detail' => 'array', 'media_type' => 'array'];
}