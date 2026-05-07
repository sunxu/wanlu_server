<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property string $date 日期
 * @property string $advertiser_id 广告主
 * @property string $app_code 应用标识
 * @property string $platform 平台jl-巨量 ks-快手
 * @property string $budget 日预算
 * @property string $cost 总花费
 * @property int $show 展示数
 * @property string $avg_show_cost 千次展示单价
 * @property int $click 点击
 * @property string $avg_click_cost 点击单价
 * @property string $click_rate 点击率（点击/展示）
 * @property int $convert 转化数
 * @property string $convert_cost 转化成本
 * @property string $convert_rate 转化率（转化/点击）
 * @property int $active 激活数
 * @property string $active_cost 激活成本
 * @property string $active_rate 激活率（激活/点击）
 * @property int $next_day_open 次留数
 * @property string $next_day_open_cost 次留成本
 * @property string $next_day_open_rate 次留率（次留/激活）
 * @property int $key_action 关键行为数
 * @property string $key_action_cost 关键行为成本
 * @property string $key_action_rate 关键行为率（关键/激活）
 * @property string $attribution_app_24h_roi 激活后24小时变现ROI
 * @property string $hour_info 小时信息
 * @property int $updated_time 更新时间
 * @property int $created_time 创建时间
 */
class ReportDailyAdvertiser extends Model
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
    protected $table = 'report_daily_advertiser';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date', 'advertiser_id', 'app_code', 'platform', 'cost',
        'show', 'avg_show_cost', 'click', 'avg_click_cost', 'click_rate',
        'convert', 'convert_cost', 'convert_rate', 'active', 'active_cost',
        'active_rate', 'next_day_open', 'next_day_open_cost', 'next_day_open_rate', 'key_action',
        'key_action_cost', 'key_action_rate', 'hour_info', 'budget', 'attribution_app_24h_roi',
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'show' => 'integer', 'click' => 'integer', 'convert' => 'integer', 'active' => 'integer', 'next_day_open' => 'integer', 'key_action' => 'integer', 'created_time' => 'integer', 'updated_time' => 'integer', 'hour_info' => 'array'];
}