<?php

declare (strict_types=1);
namespace App\ModelTools;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 
 * @property int $account_id 管家ID
 * @property string $advertiser_id 广告主ID
 * @property string $advertiser_name 广告主名称
 * @property string $app_code 应用代码
 * @property int $status 状态[0]禁用[1]启用
 * @property int $launch_type 投放类型[0]双出[1]关键行为
 * @property int $action_type 关键行为[0]不触发[1]回收率%[2]回收金额[3]网赚关键行为
 * @property string $action_revenue 关键行为:回收率、回收金额
 * @property string $action_value_mis 网赚关键行为定义
 * @property int $active_type 激活定义[1]开屏[2]归因[3]回收[4]激励视频
 * @property string $active_value 激活回收标准值
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class Advertiser extends Model
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
    protected $table = 'advertisers';
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
    protected $casts = ['id' => 'integer', 'account_id' => 'integer', 'status' => 'integer', 'launch_type' => 'integer', 'action_type' => 'integer', 'active_type' => 'integer', 'create_time' => 'integer', 'update_time' => 'integer'];
}