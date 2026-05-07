<?php

declare (strict_types=1);

namespace App\Model;

use App\Constants\AppConst;
use App\Constants\RedisConst;
use App\Utils\RedisManager;

/**
 * @property int $app_id app唯一id
 * @property string $app_code app标识
 * @property string $app_name app名称
 * @property string $abbr 平台
 * @property string $sign_secret_key 签名密钥
 * @property string $params_secret_key 参数密钥
 * @property int $status 禁用状态：1=可用，0=禁用
 * @property int $ad_status 投放状态[0]停投[1]自投[2]代投
 * @property string $ad_name 投放应用名标识
 * @property string $package_name 包名
 * @property int $type 类型[1000]工具[2000]网赚
 * @property int $sub_type 子类型[1001]WiFi[1002]清理[1003]天气[2001]短视频[2002]猜歌
 * @property int $created_time 创建时间
 * @property int $updated_time 修改时间
 * @property string $app_store 商店应用信息
 * @property int $company_id 公司主体ID
 * @property string $extra
 * @property string $ly_appid
 */
class App extends Model
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
    protected $table = 'app';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['app_code', 'app_name', 'sign_secret_key', 'params_secret_key', 'status', 'ad_status', 'package_name', 'type', 'sub_type', 'ly_appid'];

    protected $primaryKey = 'app_id';
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'app_id' => 'integer', 'status' => 'integer', 'created_time' => 'integer',
        'updated_time' => 'integer', 'extra' => 'array'
    ];

    public function auth()
    {
        return $this->hasOne(AppAuth::class, 'app_id', 'app_id');
    }

    public function users()
    {
        return $this->hasMany(UserApp::class, 'app_id', 'app_id');
    }

    public function getAppInfo(int $appId)
    {
        $appId = "app-{$appId}";
        $redis = RedisManager::get();
        $appInfo = $redis->hGet(RedisConst::APP_CACHE, $appId);
        if (!$appInfo) {
            $apps = self::get()->all();
            foreach ($apps as $app) {
                $redis->hSet(RedisConst::APP_CACHE, "app-" . data_get($app, 'app_id'), json_encode($app));
            }
            $appInfo = $redis->hGet(RedisConst::APP_CACHE, $appId);
        }

        return json_decode($appInfo ?: '[]', true);
    }

    public function appList(array $appType = [], array $appCode = [], array $putStatus = [], array $appSubType = [], array $status = [AppConst::APP_STATUS_ENABLE])
    {
        $model = App::query();

        !empty($appType) && $model->whereIn('type', $appType);
        !empty($appCode) && $model->whereIn('app_code', $appCode);
        !empty($putStatus) && $model->whereIn('ad_status', $putStatus);
        !empty($appSubType) && $model->whereIn('sub_type', $appSubType);
        !empty($status) && $model->whereIn('status', $status);

        return $model->orderBy('app_id', 'desc')
            ->pluck('app_name', 'app_code')
            ->toArray();
    }
}