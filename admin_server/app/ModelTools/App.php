<?php

declare (strict_types=1);
namespace App\ModelTools;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 
 * @property string $appid 应用ID
 * @property string $appname 应用名称
 * @property string $appcode 应用标识
 * @property string $appkey 应用秘钥
 * @property int $status 状态[0]禁用[1]配置启用[2]全启用
 * @property string $ad_name 投放应用名标识
 * @property int $ad_status 投放状态[0]停投[1]自投[2]代投
 * @property int $is_new_total 是否新的统计方式
 * @property string $um_appkey 友盟appkey
 * @property int $type 类型[1000]工具[2000]网赚
 * @property int $sub_type 子类型[1001]WiFi[1002]清理[1003]天气[2001]短视频[2002]猜歌
 * @property int $update_time 更新时间
 * @property int $create_time 创建时间
 */
class App extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'app';
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
    protected $casts = ['id' => 'integer', 'status' => 'integer', 'ad_status' => 'integer', 'is_new_total' => 'integer', 'type' => 'integer', 'sub_type' => 'integer', 'update_time' => 'integer', 'create_time' => 'integer'];
}