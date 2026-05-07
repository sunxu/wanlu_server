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
use App\Service\Placement\CsjApiService;
use App\Service\Placement\KsApiService;
use App\Service\Placement\ToponApiService;
use App\Service\Placement\YlhApiService;
use App\Utils\ValidateTrait;


class AppQueryAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'int'],//账号ID
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
            AppConst::PLATFORM_TOPON => ToponApiService::class,
        ];
        $account = PlacementAccount::find($params['account_id']);
        $class = $service_map[$account->platform];
        $Service = new $class($account);
        $list = $Service->queryApp();
        $time = time();
        foreach ($list as $row) {
            $where = [];
            $where['account_id'] = $row['account_id'];
            $where['app_id'] = $row['app_id'];

            $data = [];
            $data['app_name'] = $row['app_name'];
            $data['package_name'] = $row['package_name'];
            $data['status'] = $row['status'];
            $data['created_time'] = $time;
            $data['updated_time'] = $time;
            PlacementApp::updateOrInsert($where, $data);
        }

        $last_updated_time = PlacementApp::query()->where('account_id', $params['account_id'])->max('updated_time');//获取最后更新时间
        $list = PlacementApp::query()
            ->where('status', 1)
            ->where('updated_time', $last_updated_time)
            ->where('account_id', $params['account_id'])
            ->get(['account_id', 'app_id', 'app_name', 'package_name']);
        $data = [];
        $data['app_list'] = $list;
        return $this->responseSuccess($data);
    }
}