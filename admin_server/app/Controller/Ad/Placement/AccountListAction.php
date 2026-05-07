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
use App\Utils\ValidateTrait;
use Hyperf\Validation\Rule;

class AccountListAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::in(array_keys(AppConst::PLATFORM_NAME_MAPS))],//平台
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());
        $list = PlacementAccount::query()->where('platform', $params['platform'])->get(['id', 'platform', 'subject']);
        $data['account_list'] = $list;
        return $this->responseSuccess($data);
    }
}