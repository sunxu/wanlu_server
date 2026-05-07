<?php
declare(strict_types=1);

namespace App\Controller\Ad\Strategy;

use App\Constants\ErrorCode;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Model\AdStrategy as AdStrategyUc;
use App\Utils\ValidateTrait;

class StatusAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer'],
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());

        $model = AdStrategyUc::find($params['id']);
        if (!$model) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '数据不存在');
        }
        $model->status = $model->status == 0 ? 1 : 0;
        $model->save();

        return $this->responseSuccess();
    }
}