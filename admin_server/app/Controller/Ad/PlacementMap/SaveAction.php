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
use App\Utils\ValidateTrait;

class SaveAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer'],//ID 传了是修改，不传是新增
            'app_code' => ['required'],//app_code
            'source' => ['required'],//投放渠道
            'launch_type' => ['required'],//投放方式
            'ad_platform' => ['required'],//广告平台
            'ad_scene' => ['required'],//广告场景
            'placement_type' => ['required'],//代码位类型
            'placement_name' => ['required'],//代码位名称
            'topon_id' => ['required'],//ToponID
            'placement_id' => ['required'],//代码位ID
            'ecpm' => ['required'],//保价ecpm
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());

        $Model = $params['id'] ? AdInsured::find($params['id']) : new AdInsured();
        if (!$Model) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '数据不存在');
        }
        $source_old_map = AppConst::SOURCE_OLD_MAP;
        $launch_type_old_map = AppConst::LAUNCH_TYPE_OLD_MAP;
        $platform_old_maps = AppConst::PLATFORM_OLD_MAPS;
        $ad_type_name_map = AppConst::AD_TYPE_NAME_MAP;

        $params['media_type'] = $platform_old_maps[$params['ad_platform']];
        $params['source'] = $source_old_map[$params['source']];
        $params['lanuch_type'] = $launch_type_old_map[$params['launch_type']];
        $params['placement_type'] = $ad_type_name_map[$params['placement_type']];
        $Model->fill($params);
        $Model->save();

        $query = AdInsured::query();
        $query->where('topon_id', $Model->topon_id);
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
            $update->where('topon_id', $Model->topon_id);
            $update->where('ecpm', $ecpm);
            $update->update(['level' => $level, 'fall_level' => $fall_level]);
        }

        return $this->responseSuccess();
    }
}