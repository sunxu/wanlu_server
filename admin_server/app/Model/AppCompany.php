<?php

declare (strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Events\Creating;
use Hyperf\Database\Model\Events\Updating;

/**
 * @property int $company_id 公司ID
 * @property string $company_name 公司名
 * @property string $domain 域名
 * @property string $store_huawei 商店-华为
 * @property string $store_xiaomi 商店-小米
 * @property string $store_oppo 商店-OPPO
 * @property string $store_vivo 商店-VIVO
 * @property string $media_conf_csj 媒体-穿山甲
 * @property string $media_conf_ylh 媒体-优量汇
 * @property string $media_conf_ks 媒体-快手
 * @property string $media_conf_hw 媒体-华为
 * @property string $media_conf_vi 媒体-VIVO
 * @property string $media_conf_op 媒体-OPPO
 * @property string $media_conf_bd 媒体-百度
 * @property string $media_app_csj 媒体应用-穿山甲
 * @property string $media_app_ylh 媒体应用-优量汇
 * @property string $media_app_ks 媒体应用-快手
 * @property int $status 状态0-禁用1-启用
 * @property int $created_time 创建时间
 * @property int $updated_time 修改时间
 */
class AppCompany extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'app_company';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];
    protected $primaryKey = 'company_id';
    public $timestamps = false;
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'company_id' => 'integer',
        'status' => 'integer',
        'created_time' => 'integer',
        'updated_time' => 'integer',
        'store_huawei' => 'array',
        'store_xiaomi' => 'array',
        'store_oppo' => 'array',
        'store_vivo' => 'array',
        'media_conf_csj' => 'array',
        'media_conf_ylh' => 'array',
        'media_conf_ks' => 'array',
        'media_conf_hw' => 'array',
        'media_conf_vi' => 'array',
        'media_conf_op' => 'array',
        'media_conf_bd' => 'array',
        'media_app_csj' => 'array',
        'media_app_ylh' => 'array',
        'media_app_ks' => 'array',
    ];

    public function creating(Creating $event)
    {
        $this->setAttribute('created_time', time());
        $this->setAttribute('updated_time', time());
    }

    public function updating(Updating $event)
    {
        $this->setAttribute('updated_time', time());
    }
}