<?php

declare (strict_types=1);

namespace App\Model\Media;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property string $date 日期
 * @property string $total_revenue 总收入
 * @property string $detail 主体收入明细
 * @property int $created_time 创建时间
 * @property int $updated_time 修改时间
 */
class MediaDailyCompany extends Model
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
    protected $table = 'media_daily_company';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['date', 'total_revenue', 'detail'];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'created_time' => 'integer', 'updated_time' => 'integer', 'detail' => 'array'];
}