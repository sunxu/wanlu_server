<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Controller\Put\PlanRule;

use App\Constants\ErrorCode;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Model\Mkt\MktPlanRule;
use App\Utils\ValidateTrait;

class DeleteAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'id' => ['required']
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());
        $Model = MktPlanRule::find($params['id']);
        if (!$Model || $Model->status != 0) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '数据不存在，或者状态错误！');
        }
        $Model->deleted_time = time();
        $Model->save();

        return $this->responseSuccess();
    }
}