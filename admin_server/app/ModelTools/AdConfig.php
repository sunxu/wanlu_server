<?php

declare (strict_types=1);
namespace App\ModelTools;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 
 * @property int $app_id 应用ID
 * @property string $channel 应用渠道
 * @property string $key 策略key
 * @property string $vendor 厂商
 * @property int $source 投放渠道
 * @property int $account 关键行为账户[0]否[1]是
 * @property int $union 是否联运[0]否[1]是
 * @property string $info 策略json
 * @property int $update_time 更新时间
 * @property int $create_time 创建时间
 */
class AdConfig extends Model
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
    protected $table = 'ad_config';
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
    protected $fillable = [
        'app_id',
        'channel',
        'key',
        'vendor',
        'source',
        'account',
        'union',
        'info',
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'app_id' => 'integer', 'source' => 'integer', 'account' => 'integer', 'union' => 'integer', 'update_time' => 'integer', 'create_time' => 'integer'];
}