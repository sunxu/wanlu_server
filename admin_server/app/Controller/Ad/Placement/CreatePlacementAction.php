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
use App\ModelTools\PlacementList;
use App\Service\Placement\CsjApiService;
use App\Service\Placement\KsApiService;
use App\Service\Placement\YlhApiService;
use App\Utils\ValidateTrait;
use Hyperf\Validation\Rule;

class CreatePlacementAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'int'],//账号ID
            'app_id' => ['required'],//应用ID
            'create_key' => ['required'],//创建KEY,导出时用
            'placement_data' => ['required', 'array'],//代码位信息
            'placement_data.type' => ['required', Rule::in(array_keys(AppConst::AD_TYPE_NAME_MAP))],//代码位类型
            'placement_data.price' => ['required'],//代码位CPM
            'placement_data.name' => ['required'],//代码位名称
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());

        /*调第三方API刷新当前账户下的应用列表*/
        $service_map = [
            AppConst::PLATFORM_KS => KsApiService::class,
            AppConst::PLATFORM_CSJ => CsjApiService::class,
            AppConst::PLATFORM_YLH => YlhApiService::class,
        ];
        $account = PlacementAccount::find($params['account_id']);

        $class = $service_map[$account->platform];
        $Service = new $class($account);
        $result = $Service->create($params);
        if ($result['code'] != 200) {
            return $this->responseError($result['code'], $result['msg']);
        }

        $app_name = PlacementApp::where('account_id', $params['account_id'])->where('app_id', $params['app_id'])->value('app_name');

        $insert = [
            'create_key' => $params['create_key'],
            'account_id' => $params['account_id'],
            'platform' => $account->platform,
            'app_id' => $params['app_id'],
            'app_name' => $app_name,
            'placement_ad_type' => $params['placement_data']['type'],
            'placement_name' => $params['placement_data']['name'],
            'placement_cpm' => $params['placement_data']['price'],
            'set_cpm_result' => $result['data']['set_cpm_result'],
            'placement_id' => $result['data']['placement_id'],
            'updated_time' => time(),
            'created_time' => time(),
        ];

        PlacementList::insert($insert);

        $data = $result['data'];
        return $this->responseSuccess($data);
    }
}