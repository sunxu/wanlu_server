<?php

declare (strict_types=1);
namespace App\ModelMis;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 广告策略表
 * @property string $name 
 * @property string $app_id 应用ID
 * @property string $channel 应用渠道
 * @property string $key 策略key
 * @property string $vendor 厂商
 * @property string $source 投放渠道
 * @property int $is_action_account 关键行为账户 1是 0否
 * @property int $is_union 是否联运 1是 0否
 * @property string $info 策略json
 * @property int $updated_time 更新时间
 * @property int $created_time 创建时间
 */
class AdStrategy extends Model
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
    protected $table = 'ad_strategy';
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mis';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'app_id',
        'channel',
        'key',
        'vendor',
        'source',
        'is_action_account',
        'is_union',
        'info'
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'is_action_account' => 'integer', 'is_union' => 'integer', 'updated_time' => 'integer', 'created_time' => 'integer'];
}