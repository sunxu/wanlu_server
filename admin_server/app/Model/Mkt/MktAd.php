<?php

declare (strict_types=1);

namespace App\Model\Mkt;

use Hyperf\DbConnection\Model\Model;

/**
 * @property string $ad_id 广告ID
 * @property string $ad_name 广告名称
 * @property string $advertiser_id 广告主ID
 * @property string $project_id 项目ID
 * @property string $app_code 应用
 * @property int $put_status 投放状态 0-默认 1-投放中 2-暂停
 * @property string $create_time 计划创建时间
 * @property string $update_time 计划修改时间
 * @property string $budget_amount 预算金额
 * @property int $created_time 创建时间
 * @property int $updated_time 修改时间
 * @property int $deleted_time 删除时间
 * @property int $is_mark 是否标记:[0]未标记[1]已标记
 */
class MktAd extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    public $incrementing = false;
    protected $dateFormat = 'U';
    protected $primaryKey = 'ad_id';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mkt_ad';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ad_id', 'ad_name', 'advertiser_id', 'project_id', 'app_code', 'put_status',
        'create_time', 'update_time', 'budget_amount'
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['put_status' => 'integer', 'created_time' => 'integer', 'updated_time' => 'integer', 'deleted_time' => 'integer', 'is_mark' => 'integer'];

    public function advertiser()
    {
        return $this->hasOne(MktAdvertiser::class, 'advertiser_id', 'advertiser_id');
    }
}