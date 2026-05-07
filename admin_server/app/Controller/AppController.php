<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\AppConst;
use App\Model\App;
use App\Request\App\EditAuthRequest;
use App\Service\AppService;
use Hyperf\Context\Context;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\View\RenderInterface;

class AppController extends AbstractController
{
    /**
     * @Inject
     * @var AppService
     */
    protected $appService;

    public function index()
    {
        $params = $this->request->all();
        $userInfo = Context::get('_userInfo');
        $authUserId = data_get($userInfo, 'user_id');
        data_set($params, 'auth_user_id', $authUserId);
        $list = $this->appService->getAppList($params);

        return $this->responsePaginator($list->items(), $list);
    }

    public function appList()
    {
        $list = $this->appService->appList();

        return $this->responseRecord($list);
    }

    public function editAuth(int $appId, EditAuthRequest $request)
    {
        $this->appService->editAppAuth($appId, $request->all());

        return $this->responseSuccess();
    }

    public function editStatus(int $appId)
    {
        return $this->appService->editStatus($appId) ? $this->responseSuccess() : $this->responseError();
    }

    public function editPutStatus(int $appId)
    {
        return $this->responseSuccess($this->appService->editPutStatus($appId));
    }

    public function putStatus(string $appCode)
    {
        return $this->responseSuccess($this->appService->putStatus($appCode));
    }

    public function editPutStatus2(int $appId)
    {
        return $this->responseSuccess($this->appService->editPutStatus2($appId));
    }


    public function getAppSecretKey(string $appCode, string $token)
    {
        if ($token != 'aS8QjlNob8pHvzfj') {
            return $this->responseSuccess();
        }
        $app = App::where('app_code', $appCode)->first();
        $appTest = Db::connection('test')->table('app')->where('app_code', $appCode)->first();

        $secretKeyPro = [
            'id' => data_get($app, 'app_id'),
            'key' => data_get($app, 'app_code'),
            'app_name' => data_get($app, 'app_name'),
            'appid' => data_get($app, 'sign_secret_key'),
            'secret' => data_get($app, 'params_secret_key'),
            'ad_status' => AppConst::APP_PUT_STATUS_MAP[data_get($app, 'ad_status')] ?? '',
        ];
        $secretKeyTest = [
            'id' => data_get($appTest, 'app_id'),
            'key' => data_get($appTest, 'app_code'),
            'appid' => data_get($appTest, 'sign_secret_key'),
            'secret' => data_get($appTest, 'params_secret_key'),
        ];
        $Render = di(RenderInterface::class);

        return $Render->render('app_secret_key', ['app_pro' => $secretKeyPro, 'app_test' => $secretKeyTest]);
    }

    public function saveData()
    {
        $this->appService->saveData($this->request->all());
        return $this->responseSuccess();
    }

    public function block(string $appCode)
    {
        $params = $this->request->all();
        $this->appService->saveExtra($appCode, ['block_channel' => $params['channel'] ?? []]);
        return $this->responseSuccess();
    }
}