<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/17
 */

namespace App\Controller\Ad\Placement;

use App\Controller\AbstractAction;
use App\ModelTools\PlacementApp;
use App\Utils\ValidateTrait;


class AppListAction extends AbstractAction
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

        $last_updated_time = PlacementApp::query()->where('account_id', $params['account_id'])->max('updated_time');//获取最后更新时间
        $list = PlacementApp::query()
            ->where('status', 1)
            ->where('updated_time', $last_updated_time)
            ->where('account_id', $params['account_id'])
            ->get(['account_id', 'app_id', 'app_name', 'package_name']);
        $data['app_list'] = $list;
        return $this->responseSuccess($data);
    }
}