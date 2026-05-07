<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Controller\Put\Advertiser;

use App\Constants\ErrorCode;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Model\Mkt\MktAdvertiser as Advertiser;
use App\Utils\ValidateTrait;

class BindAppAction extends AbstractAction
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
        $Model->app_code = $params['app_code'];
        $Model->save();

        return $this->responseSuccess();
    }
}