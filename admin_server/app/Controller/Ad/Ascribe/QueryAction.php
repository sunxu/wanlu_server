<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/17
 */

namespace App\Controller\Ad\Ascribe;

use App\Controller\AbstractAction;
use App\Constants\AppConst;
use App\Constants\ErrorCode;
use App\Constants\UserConst;
use App\Exception\ValidationException;
use App\Model\Mkt\MktAdvertiser as Advertiser;
use App\Model\App as AppUc;
use App\ModelMis\AdPutClick;
use App\ModelMis\UserAccount;
use App\ModelMis\UserDevice;
use App\ModelTools\Device;
use App\Utils\ValidateTrait;


class QueryAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'app_code' => ['required'],//app_code
            'user_id' => ['required'],//设备或者用户ID
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
            $query = (new Device())->setSuffix($App->app_code)->newQuery();
            $query->where('android_id', $params['user_id'])
                ->orWhere('oaid', $params['user_id']);
            $device = $query->select([
                'android_id as user_id',
                'oaid',
                'channel as channel_id',
                'create_time as created_time',
                'version as app_version',
                'vendor',
                'account_id as advertiser_id',
                'source'
            ])->first();
        } else {//网赚
            $Model = (new UserAccount())->setSuffix($App->app_code);
            $deviceTable = (new UserDevice())->getTable();
            $putClickTable = (new AdPutClick())->setSuffix($App->app_code)->getTable();
            $query = $Model->newQuery();
            $query->from($Model->getTable() . ' as a');
            $query->leftJoin($deviceTable . ' as b', 'a.user_id', '=', 'b.user_id');
            $query->leftJoin($putClickTable . ' as c', 'a.user_id', '=', 'c.user_id');
            $query->where('a.user_id', $params['user_id']);
            $device = $query->select([
                'a.user_id',
                'a.channel_id',
                'a.created_time',
                'a.app_version',
                'a.vendor_type as vendor',
                'b.is_except',
                'c.advertiser_id',
                'c.source'
            ])->first();
        }

        if ($device) {
            $device = $device->toArray();
            $device['is_except'] = $device['is_except'] ?? UserConst::IS_EXCEPT['normal'];
            $device['vendor'] = strtolower($device['vendor']);
            $device['created_time'] = date('Y-m-d H:i:s', $device['created_time']);
            $device['source'] = $device['source'] ?: '';
            $device['source'] = array_flip(AppConst::SOURCE_OLD_MAP)[$device['source']] ?? $device['source'];
            $device['advertiser_id'] = $device['advertiser_id'] ?: '';
            $device['advertiser_name'] = '';
            $device['launch_type'] = '';
            if ($device['advertiser_id']) {
                /** @var Advertiser $advertiser */
                $advertiser = Advertiser::query()->where('advertiser_id', $device['advertiser_id'])->first();
                $device['advertiser_name'] = $advertiser ? $advertiser->advertiser_name : '';
                $device['launch_type'] = $advertiser ? $advertiser->launch_type : '';
            }
        }
        $data = $device;
        return $this->responseSuccess($data);
    }
}