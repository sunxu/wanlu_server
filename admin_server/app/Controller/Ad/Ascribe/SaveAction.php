<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/17
 */

namespace App\Controller\Ad\Ascribe;

use App\Constants\AppConst;
use App\Constants\ErrorCode;
use App\Constants\RedisConst;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Model\App as AppUc;
use App\ModelMis\AdPutClick;
use App\ModelMis\UserAccount;
use App\ModelTools\Device;
use App\Utils\ValidateTrait;
use Hyperf\Validation\Rule;


class SaveAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'app_code' => ['required'],//app_code
            'user_id' => ['required'],//设备或者用户ID
            'source' => ['nullable', Rule::in(array_keys(AppConst::SOURCE_NAME_MAP))],//投放渠道
            'launch_type' => ['nullable', Rule::in(array_keys(AppConst::LAUNCH_TYPE_NAME_MAP))],//投放方式
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());

        /** @var AppUc $App */
        $App = AppUc::query()->where('app_code', $params['app_code'])->first();
        if (!$App) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '不存在或未配置此应用');
        }

        if ($App->type == AppConst::APP_TYPE_TOOLS) {//工具
            /** @var Device $DeviceModel */
            $DeviceModel = (new Device())->setSuffix($App->app_code)->newQuery()->where('android_id', $params['user_id'])->first();
            if ($DeviceModel) {
                $DeviceModel->ad_time = isset($params['source']) ? time() : 0;
                $DeviceModel->source = isset($params['source']) ? AppConst::SOURCE_OLD_MAP[$params['source']] : '';
                $DeviceModel->account_id = isset($params['source']) ? AppConst::LAUNCH_TYPE_TEST_ADVERTISER_ID_MAP[$params['launch_type']] : '';
                $DeviceModel->location = array_merge((array)$DeviceModel->location, ['white' => 1]);
                $DeviceModel->save();
            }
        } else {//网赚
            /** @var UserAccount $AccountModel */
            $AccountModel = (new UserAccount())->setSuffix($App->app_code)->newQuery()->where('user_id', $params['user_id'])->first();
            if ($AccountModel) {
                if (isset($params['source'])) {//添加或修改
                    $PutClickModel = (new AdPutClick())->setSuffix($App->app_code)->newQuery()->where('user_id', $params['user_id'])->first();
                    if (!$PutClickModel) {
                        $PutClickModel = (new AdPutClick())->setSuffix($App->app_code);
                        $PutClickModel->user_id = $params['user_id'];
                        $PutClickModel->log_id = $params['user_id'];
                        $PutClickModel->callback_url = '';
                        $PutClickModel->install_time = $AccountModel->created_time;
                        $PutClickModel->click_time = time();
                    }
                    $PutClickModel->source = $params['source'];
                    $PutClickModel->advertiser_id = AppConst::LAUNCH_TYPE_TEST_ADVERTISER_ID_MAP[$params['launch_type']];
                    $PutClickModel->save();

                    $AccountModel->advertiser_id = $PutClickModel->advertiser_id;
                    $AccountModel->source = $PutClickModel->source;
                    $AccountModel->launch_type = $params['launch_type'];
                    $AccountModel->save();

                } else {//删除
                    $PutClickModel = (new AdPutClick())->setSuffix($App->app_code)->newQuery()->where('user_id', $params['user_id'])->first();
                    if ($PutClickModel) {
                        $PutClickModel->delete();
                    }

                    $AccountModel->advertiser_id = null;
                    $AccountModel->source = null;
                    $AccountModel->launch_type = null;
                    $AccountModel->save();
                }

                /*清除缓存*/
                $RedisAd = redis_client(RedisConst::POOL_NAME_MIS_AD);
                $RedisUser = redis_client(RedisConst::POOL_NAME_MIS_USER);
                $put_click_key = sprintf(RedisConst::MODEL_CACHE_KEY, (new AdPutClick())->setSuffix($App->app_code)->getTable(), $params['user_id']);
                $account_key = sprintf(RedisConst::USER_INFO, $params['user_id'], $App->app_code);
                $RedisAd->del($put_click_key);
                $RedisUser->del($account_key);
            }
        }

        return $this->responseSuccess();
    }
}