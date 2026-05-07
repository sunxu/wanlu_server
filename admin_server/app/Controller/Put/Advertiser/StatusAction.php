<?php
declare(strict_types=1);

namespace App\Controller\Put\Advertiser;

use App\Constants\ErrorCode;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Model\Mkt\MktAdvertiser as Advertiser;
use App\Utils\ValidateTrait;

class StatusAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'app_code' => ['required'],//app_code
            'advertiser_id' => ['required'],//app_code
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());

        $Model = Advertiser::find($params['advertiser_id']);
        if (!$Model) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '数据不存在');
        }

        $Model->status = $Model->status == 0 ? 1 : 0;
        $Model->save();

        return $this->responseSuccess();
    }
}