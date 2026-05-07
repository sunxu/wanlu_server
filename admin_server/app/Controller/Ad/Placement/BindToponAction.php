<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/17
 */

namespace App\Controller\Ad\Placement;

use App\Constants\AppConst;
use App\Controller\AbstractAction;
use App\ModelTools\PlacementAccount;
use App\ModelTools\PlacementApp;
use App\ModelTools\PlacementToponList;
use App\Service\Placement\ToponApiService;
use App\Utils\ValidateTrait;
use Hyperf\Validation\Rule;


class BindToponAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'int'],//账号ID
            'app_id' => ['required'],//应用ID
            'create_key' => ['required'],//创建KEY,导出时用
            'placement_data' => ['required', 'array'],//代码位信息
            'placement_data.platform' => ['required', Rule::in(array_keys(AppConst::PLATFORM_NAME_MAPS))],//广告平台
            'placement_data.placement_id' => ['required'],//代码位ID
            'placement_data.placement_name' => ['required'],//代码位名称
            'placement_data.placement_type' => ['required',  Rule::in(array_keys(AppConst::AD_TYPE_NAME_MAP))],//代码位类型
            'placement_data.topon_id' => ['required'],//ToponID
            'placement_data.topon_price' => ['required'],//Topon价格
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());

        $account = PlacementAccount::find($params['account_id']);

        $Service = new ToponApiService($account);
        $result = $Service->bind($params);
        if ($result['code'] != 200) {
            return $this->responseError($result['code'], $result['msg']);
        }

        $app_name = PlacementApp::where('account_id', $params['account_id'])->where('app_id', $params['app_id'])->value('app_name');

        $insert = [
            'create_key' => $params['create_key'],
            'account_id' => $params['account_id'],
            'app_id' => $params['app_id'],
            'app_name' => $app_name,
            'placement_ad_type' => $params['placement_data']['placement_type'],
            'placement_name' => $params['placement_data']['placement_name'],
            'placement_id' => $params['placement_data']['placement_id'],
            'topon_id' => $params['placement_data']['topon_id'],
            'topon_cpm' => $params['placement_data']['topon_price'],
            'updated_time' => time(),
            'created_time' => time(),
        ];

        PlacementToponList::insert($insert);

        $data = $result['data'];
        return $this->responseSuccess($data);
    }
}