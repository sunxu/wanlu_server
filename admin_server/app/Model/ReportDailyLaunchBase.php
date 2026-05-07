<?php

declare (strict_types=1);

namespace App\Model;

/**
 * @property float $imputed_revenue 估算回收
 * @property float $active_revenue 活跃-估算回收
 */
class ReportDailyLaunchBase extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'report_daily_launch_base';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'created_date', 'app_code', 'cost', 'active', 'convert', 'next_day_open', 'guiyin_success',
        'increment_install', 'increment_next_day_open', 'increment_show', 'install_huawei', 'install_xiaomi',
        'install_oppo', 'install_vivo', 'imputed_revenue', 'active_revenue', 'dau', 'deu', 'show', 'guiyin_success_new',
        'increment_install_new', 'version_install', 'version_guiyin', 'media_revenue', 'media_detail'
    ];

    public $timestamps = false;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'media_detail' => 'array'
    ];
}