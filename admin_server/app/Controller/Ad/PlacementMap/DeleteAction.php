<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Controller\Ad\PlacementMap;

use App\Constants\ErrorCode;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\ModelTools\AdInsured;
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

        $Model = AdInsured::find($params['id']);
        if(!$Model){
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '数据不存在');
        }
        $topon_id = $Model->topon_id;
        $Model->delete();


        $query = AdInsured::query();
        $query->where('topon_id', $topon_id);
        $query->where('delete_time', 0);
        $query->where('ecpm', '>=', 0);
        $query->groupBy(['ecpm']);
        $query->orderBy('ecpm');
        $ecpms = $query->pluck('ecpm');

        $count = count($ecpms);
        $_level = 0;
        foreach ($ecpms as $index => $ecpm) {
            $fall_level = $count - $index;
            $level = $ecpm == 0 ? 0 : --$_level;
            echo $ecpm . '|' . $level . '|' . $fall_level . PHP_EOL;
            $update = AdInsured::query();
            $update->where('topon_id', $topon_id);
            $update->where('ecpm', $ecpm);
            $update->update(['level' => $level, 'fall_level' => $fall_level]);
        }

        return $this->responseSuccess();
    }
}