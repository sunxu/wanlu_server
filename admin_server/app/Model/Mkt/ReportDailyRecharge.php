<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use Hyperf\DbConnection\Model\Model;

class ReportDailyRecharge extends Model
{
    public $timestamps = false;

    protected $table = 'report_daily_recharge';

    protected $fillable = ['report_date', 'app_code', 'total_amount', 'info'];

    protected $casts = ['info' => 'array'];
}