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

class ExportAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'source' => ['nullable'],//投放渠道
            'launch_type' => ['nullable'],//投放方式
            'ad_platform' => ['nullable'],//广告平台
            'search_id' => ['nullable'],//ID搜索
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

        $source_old_map = AppConst::SOURCE_OLD_MAP;
        $_source_old_map = array_flip(AppConst::SOURCE_OLD_MAP);

        $launch_type_old_map = AppConst::LAUNCH_TYPE_OLD_MAP;
        $_launch_type_old_map = array_flip(AppConst::LAUNCH_TYPE_OLD_MAP);

        $platform_old_maps = AppConst::PLATFORM_OLD_MAPS;
        $_platform_old_maps = array_flip(AppConst::PLATFORM_OLD_MAPS);

        $_ad_type_name_map = array_flip(AppConst::AD_TYPE_NAME_MAP);

        $query = AdInsured::query();
        $query->where('app_code', $params['app_code']);
        $query->where('delete_time', 0);

        /*投放渠道筛选*/
        $source = data_get($params, 'source');
        $query->when(isset($source), function ($query) use ($source, $source_old_map) {
            $source = $source_old_map[$source];
            $query->where('source', $source);
        });

        /*投放方式筛选*/
        $launch_type = data_get($params, 'launch_type');
        $query->when(isset($launch_type), function ($query) use ($launch_type, $launch_type_old_map) {
            $launch_type = $launch_type_old_map[$launch_type];
            $query->where('lanuch_type', $launch_type);
        });

        /*广告平台筛选*/
        $ad_platform = data_get($params, 'ad_platform');
        $query->when(isset($ad_platform), function ($query) use ($ad_platform, $platform_old_maps) {
            $ad_platform = $platform_old_maps[$ad_platform];
            $query->where('media_type', $ad_platform);
        });

        /*ID搜索筛选*/
        $search_id = data_get($params, 'search_id');
        $query->when(isset($search_id), function ($query) use ($search_id) {
            $query->where(function ($query) use ($search_id) {
                $query->where('topon_id', $search_id);
                $query->orWhere('placement_id', $search_id);
            });
        });

        $query->select(['id', 'app_code', 'media_type as ad_platform', 'source', 'lanuch_type as launch_type', 'ad_scene', 'placement_type', 'placement_name', 'topon_id', 'placement_id', 'ecpm', 'level', 'fall_level', 'create_time', 'update_time']);
        $query->orderBy('create_time', 'desc');
        $_list = $query->get();
        $list = [];

        $platformNameMaps = AppConst::PLATFORM_NAME_MAPS;
        $adTypeNameMap = AppConst::AD_TYPE_NAME_MAP;
        $sourceNameMap = AppConst::SOURCE_NAME_MAP;
        $launchTypeNameMap = AppConst::LAUNCH_TYPE_NAME_MAP;

        foreach ($_list as $item) {
            $item = collect($item)->toArray();
            $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
            $item['update_time'] = date('Y-m-d H:i:s', $item['update_time']);
            $item['ad_platform'] = $_platform_old_maps[$item['ad_platform']] ?? '';
            $item['ad_platform_name'] = $platformNameMaps[$item['ad_platform']] ?? '';
            $item['source'] = $_source_old_map[$item['source']] ?? '';
            $item['source_name'] = $sourceNameMap[$item['source']] ?? '';
            $item['launch_type'] = $_launch_type_old_map[$item['launch_type']] ?? '';
            $item['launch_type_name'] = $launchTypeNameMap[$item['launch_type']] ?? '';
            $item['placement_type'] = $_ad_type_name_map[$item['placement_type']] ?? '';
            $item['placement_type_name'] = $adTypeNameMap[$item['placement_type']] ?? '';
            $item['app_name'] = $App->app_name;

            $list[] = $item;
        }

        $data['list'] = $list;
        return $this->responseSuccess($data);
    }
}