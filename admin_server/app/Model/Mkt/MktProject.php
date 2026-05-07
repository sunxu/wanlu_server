<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use Hyperf\DbConnection\Model\Model;

/**
 * @property string $project_id 项目ID
 * @property string $project_name 项目名称
 * @property string $advertiser_id 广告主ID
 * @property string $app_code 应用
 * @property int $put_status 投放状态 0-默认 1-投放中 2-暂停
 * @property string $create_time 项目创建时间
 * @property string $update_time 项目修改时间
 * @property string $budget_mode 预算模式
 * @property string $budget_amount 预算金额
 * @property string $schedule_start 投放开始
 * @property string $schedule_end 投放结束
 * @property string $schedule_time 投放时段 jl-48*7 ks-24*7
 * @property int $created_time 创建时间
 * @property int $updated_time 修改时间
 * @property int $deleted_time 删除时间
 * @property int $is_mark 是否标记:[0]未标记[1]已标记
 */
class MktProject extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    public $incrementing = false;
    protected $dateFormat = 'U';
    protected $primaryKey = 'project_id';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mkt_project';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'project_id', 'project_name', 'advertiser_id', 'app_code', 'put_status',
        'create_time', 'update_time', 'budget_mode', 'budget_amount', 'schedule_start',
        'schedule_end', 'schedule_time',
        'alert_status'
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['put_status' => 'integer', 'created_time' => 'integer', 'updated_time' => 'integer', 'deleted_time' => 'integer', 'is_mark' => 'integer', 'alert_status' => 'integer'];

    public function ad()
    {
        return $this->hasMany(MktAd::class, 'project_id', 'project_id');
    }
}