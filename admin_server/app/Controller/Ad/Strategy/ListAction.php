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
use App\Model\App as AppUc;
use App\Model\AdStrategy as AdStrategyUc;
use App\Utils\ValidateTrait;

class ListAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'app_code' => ['required'],//app_code
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());

        /** @var AppUc $App */
        $App = AppUc::query()->where('app_code', $params['app_code'])->first();
        if (!$App) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '不存在或未配置此应用');
        }

        $query = AdStrategyUc::query();
        $query->where('app_code', $App->app_code);
        $query->where('deleted_time', 0);

        $list = $query->get()->map(function ($row) {
            /** @var AdStrategyUc $row */
            $row->channel = json_decode($row->channel, true);
            $row->key = json_decode($row->key, true);
            $row->vendor = json_decode($row->vendor, true);
            $row->source = json_decode($row->source, true);
            $row->launch_type = json_decode($row->launch_type, true);
            $placement = json_decode($row->placement ?? '[]', true);
            foreach ($row->adType as $type) {
                $row->$type = $placement[$type] ?? '';
            }
            $row->created_time = date('Y-m-d H:i:s', $row->created_time);
            $row->updated_time = date('Y-m-d H:i:s', $row->updated_time);
            return $row;
        });

        $data = [];
        $data['list'] = $list;
        return $this->responseSuccess($data);
    }


}