<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Controller\Ad\PlacementMap;

use App\Constants\ErrorCode;
use App\Constants\AppConst;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\ModelTools\AdInsured;
use App\Model\App as AppUc;
use App\Utils\ValidateTrait;

class ImportAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'data_list' => ['required', 'array'],//数据列表
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

        $topon_id_list = collect($params['data_list'])->pluck('topon_id')->unique()->toArray();//不同的toponID集合

        $success = 0;
        $fail = 0;
        foreach ($params['data_list'] as $row) {
            $row['source'] = array_flip(AppConst::SOURCE_NAME_MAP)[$row['source']];
            $row['source'] = AppConst::SOURCE_OLD_MAP[$row['source']];

            $row['ad_platform'] = array_flip(AppConst::PLATFORM_NAME_MAPS)[$row['ad_platform']];
            $row['ad_platform'] = AppConst::PLATFORM_OLD_MAPS[$row['ad_platform']];
            $row['media_type'] = $row['ad_platform'];

            $row['launch_type'] = array_flip(AppConst::LAUNCH_TYPE_NAME_MAP)[$row['launch_type']];
            $row['launch_type'] = AppConst::LAUNCH_TYPE_OLD_MAP[$row['launch_type']];
            $row['lanuch_type'] = $row['launch_type'];
            $row['app_code'] = $params['app_code'];

            $Model = new AdInsured();
            $Model->fill($row);
            try {
                $Model->save();
                $success++;
            } catch (\Exception $e) {
                $fail++;
            }
        }

        foreach ($topon_id_list as $topon_id) {
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
        }


        $data = [];
        $data['success'] = $success;
        $data['fail'] = $fail;
        return $this->responseSuccess($data);
    }
}