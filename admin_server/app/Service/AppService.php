<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\AppConst;
use App\Constants\AppStoreConst;
use App\Constants\UserConst;
use App\Library\AppStore\Huawei;
use App\Library\AppStore\Oppo;
use App\Library\AppStore\Vivo;
use App\Library\AppStore\Xiaomi;
use App\Model\App;
use App\Model\AppAuth;
use App\Model\AppStore;
use App\Model\UserApp;
use App\Utils\Log;
use Hyperf\DbConnection\Db;

class AppService
{
    public function getAppList(array $params)
    {
        $app = App::with(['auth', 'users']);
        if ($authUserId = data_get($params, 'auth_user_id')) {
            $userInfo = make(UserService::class)->getUserInfo($authUserId);
            if (data_get($userInfo, 'is_super') == UserConst::USER_TYPE['normal']) {
                $app = $app->whereHas('users', function ($query) use ($authUserId) {
                    $query->where('user_id', $authUserId);
                });
            }
        }
        return $app->when($name = data_get($params, 'app_name'), function ($query) use ($name) {
            $query->where('app_name', 'like', "%{$name}%")
                ->orWhere('app_code', $name)
                ->orWhere('sign_secret_key', $name);
        })
            ->when(is_numeric($adStatus = data_get($params, 'ad_status')), function ($query) use ($adStatus) {
                $query->where('ad_status', $adStatus);
            })
            ->when(is_numeric($status = data_get($params, 'status')), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->where('abbr', '<>', 'web')
            ->orderBy('ad_status', 'desc')
            ->orderBy('status', 'desc')
            ->orderBy('updated_time', 'desc')
            ->orderBy('app_id', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)));
    }

    public function editAppAuth(int $appId, array $params)
    {
        $appAuth = AppAuth::where('app_id', $appId)->first();
        if (!$appAuth) {
            $appAuth = new AppAuth();
            $appAuth->app_id = $appId;
        }
        $appAuth->fill([
            'pages' => data_get($params, 'pages', []),
            'apis' => data_get($params, 'apis', []),
        ]);
        $appAuth->saveOrFail();
    }

    public function getRelationUserId(int $appId): array
    {
        return UserApp::where('app_id', $appId)->get('user_id')->pluck('user_id')->toArray();
    }

    public function getUserApp(int $userId)
    {
        $app = App::with(['users']);
        $userInfo = make(UserService::class)->getUserInfo($userId);
        if (data_get($userInfo, 'is_super') == UserConst::USER_TYPE['normal']) {
            $app = $app->whereHas('users', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            });
        }

        return $app->pluck('app_name', 'app_code')->toArray();
    }

    public function auditNotify(string $vendor)
    {
        if (!is_product()) {
            return;
        }
        $vendors = [
            'huawei' => [
                'column' => 'store_config_huawei',
                'class' => Huawei::class,
            ],
            'vivo' => [
                'column' => 'store_config_vivo',
                'class' => Vivo::class,
            ],
//            'xiaomi' => [
//                'column' => 'store_config_xiaomi',
//                'class' => Xiaomi::class,
//            ],
            'oppo' => [
                'column' => 'store_config_oppo',
                'class' => Oppo::class,
            ]
        ];
        $vendorConf = $vendors[$vendor];
        $apps = AppStore::where('is_enable_notify', AppStoreConst::IS_ENABLE_NOTIFY['enable'])->get()->all();
        foreach ($apps as $app) {
            if (data_get($app, $vendorConf['column'])) {
                try {
                    $store = make($vendorConf['class'], [data_get($app, 'app_id')]);
                    if ($store->detectChange()) {
                        $store->sendMessage();
                    }
                } catch (\Exception $exception) {
                    Log::system('error', $vendor . '审核信息查询失败', ['message' => $exception->getMessage(), 'file' => $exception->getFile()]);
                }
            }
        }
    }

    public function editAdStatus(string $appCode, int $status)
    {
        if (!in_array($status, AppConst::APP_PUT_STATUS_ARR)) {
            return false;
        }

        $app = App::query()->where('app_code', $appCode)->first();

        $app->ad_status = $status;

        return $app->save();
    }

    public function putStatus(string $appCode)
    {
        $app = App::query()->where('app_code', $appCode)->first();
        $app->ad_status = $app->ad_status == AppConst::APP_PUT_STATUS_SELF ? AppConst::APP_PUT_STATUS_STOP : AppConst::APP_PUT_STATUS_SELF;
        $app->save();
        return ['ad_status' => $app->ad_status];
    }

    public function editPutStatus(int $appId)
    {
        $app = App::query()->where('app_id', $appId)->first();
        $app->ad_status = $app->ad_status == AppConst::APP_PUT_STATUS_SELF ? AppConst::APP_PUT_STATUS_STOP : AppConst::APP_PUT_STATUS_SELF;
        $app->save();
        return ['ad_status' => $app->ad_status];
    }

    public function editPutStatus2(int $appId)
    {
        $app = App::query()->where('app_id', $appId)->first();
        $app->ad_status = $app->ad_status == AppConst::APP_PUT_STATUS_AGENCY ? AppConst::APP_PUT_STATUS_STOP : AppConst::APP_PUT_STATUS_AGENCY;
        $app->save();
        return ['ad_status' => $app->ad_status];
    }

    public function editStatus(int $appId)
    {
        $app = App::query()->where('app_id', $appId)->first();
        $app->status = $app->status == AppConst::APP_STATUS_ENABLE ? AppConst::APP_STATUS_DISABLE : AppConst::APP_STATUS_ENABLE;
        return $app->save();
    }

    public function appList()
    {
        return App::select(['app_name', 'app_code'])
            ->where('status', AppConst::APP_STATUS_ENABLE)
            ->get()->all();
    }

    public function saveData($params = [])
    {
        $appId = data_get($params, 'app_id');
        if ($appId) {
            $app = App::query()->where('app_id', $appId)->first();
            if (!$app) {
                throw new \Exception('应用不存在');
            }

            return $app->update([
                'app_name' => data_get($params, 'app_name'),
                'package_name' => data_get($params, 'package_name'),
                'status' => data_get($params, 'status'),
                'ad_status' => data_get($params, 'ad_status'),
            ]);
        }

        $appCode = strtolower(data_get($params, 'app_code'));
        $defaultCode = 'default';
        if (App::query()
            ->where('app_code', $appCode)
            ->value('app_id')) {
            throw new \Exception("app_code:{$appCode} 已存在");
        }

        $appDefault = App::query()->where('app_code', $defaultCode)->first();
        if (!$appDefault) {
            throw new \Exception("模版应用不存在");
        }

        Db::beginTransaction();
        try {
            (new App())->fill([
                'sign_secret_key' => substr(md5(uniqid() . time()), 0, 16),
                'params_secret_key' => md5(uniqid() . time()),
                'app_code' => $appCode,
                'app_name' => data_get($params, 'app_name', $appCode),
                'type' => data_get($appDefault, 'type'),
                'sub_type' => data_get($appDefault, 'sub_type'),
            ])->save();
        } catch (\Exception $e) {
            Db::rollBack();
            throw new \Exception($e->getMessage());
        }

        try {
            Db::connection('tools')->table('app_version')
                ->insertOrIgnore([
                    'app_code' => $appCode,
                    'version' => '1.0.0',
                    'audit_status' => '[]',
                    'create_time' => time()
                ]);
        } catch (\Exception $e) {
            Db::rollBack();
            throw new \Exception($e->getMessage());
        }

        // 创建应用表
        $list = Db::connection('tools')->select('show tables');
        foreach ($list as $item) {
            foreach ($item as $table) {
                if (strstr($table, "app_{$defaultCode}")) {
                    $newTable = str_replace($defaultCode, $appCode, $table);
                    Db::connection('tools')->statement("
                        CREATE TABLE IF NOT EXISTS {$newTable} LIKE {$table}
                    ");
                }
            }
        }

        Db::commit();
    }

    public function saveExtra(string $appCode, array $data)
    {
        $app = App::query()->where('app_code', $appCode)->first();
        if (!$app) {
            throw new \Exception("应用不存在");
        }
        $app->extra = array_merge($app->extra ?? [], $data);
        return $app->save();
    }

    public function getExtra(string $appCode, string $field, $default = null)
    {
        $app = App::query()->where('app_code', $appCode)->first();
        if (!$app) {
            throw new \Exception("应用不存在");
        }
        return data_get($app->extra, $field, $default);
    }

    public function getPutStatus(string $appCode)
    {
        $app = App::query()->where('app_code', $appCode)->first();
        if (!$app) {
            throw new \Exception("应用不存在");
        }
        $putStatus = $app->ad_status;
        return [
            'put_status' => $putStatus,
            'put_status_name' => AppConst::APP_PUT_STATUS_MAP[$app->ad_status] ?? '未知'
        ];
    }
}