<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Controller\Ad\Strategy;

use App\Constants\ErrorCode;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Model\AdStrategy as AdStrategyUc;
use App\Utils\ValidateTrait;

class DeleteAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer']
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());

        $model = AdStrategyUc::find($params['id']);
        if (!$model) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '数据不存在');
        }
        $model->deleted_time = time();
        $model->save();

        return $this->responseSuccess();
    }
}