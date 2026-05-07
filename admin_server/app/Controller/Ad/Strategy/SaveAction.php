<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Controller\Ad\Strategy;

use App\Constants\AppConst;
use App\Constants\ErrorCode;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Model\App as AppUc;
use App\Model\AdStrategy as AdStrategyUc;
use App\Utils\ValidateTrait;

class SaveAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        $adType = [];
        foreach ((new AdStrategyUc())->adType as $type) {
            $adType[$type] = ['nullable'];
        }
        return array_merge([
            'id' => ['nullable', 'integer'],//ID 传了是修改，不传是新增
            'app_code' => ['required'],//app_code
            'source' => ['required', 'array'],//投放渠道
            'launch_type' => ['nullable', 'array'],//投放方式
            'key' => ['nullable', 'array'],//策略KEY
            'channel' => ['nullable', 'array'],//渠道
            'vendor' => ['nullable', 'array'],//厂商
            'info' => ['nullable'],//策略
        ], $adType);
    }

    public function default(): array
    {
        return [
            'key' => [],
            'channel' => [],
            'vendor' => [],
            'launch_type' => [],
            'info' => '{}',
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());

        foreach ($params['channel'] as &$channel) {
            $channel = (int)$channel;
        }

        foreach ($params['launch_type'] as &$launch_type) {
            $launch_type = (int)$launch_type;
        }

        $info = json_decode($params['info'], true);
        if (json_last_error() != JSON_ERROR_NONE) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '策略格式错误');
        }

        /** @var AppUc $App */
        $App = AppUc::query()->where('app_code', $params['app_code'])->first();
        if (!$App) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '不存在或未配置此应用');
        }

        $name = $this->createName($App->app_name, $params['source'], $params['launch_type'], $params['key']);

        $params['name'] = $name;
        $params['channel'] = json_encode($params['channel']);
        $params['key'] = json_encode($params['key']);
        $params['vendor'] = json_encode($params['vendor']);
        $params['launch_type'] = json_encode($params['launch_type']);
        $params['source'] = json_encode($params['source']);


        $Model = isset($params['id']) ? AdStrategyUc::find($params['id']) : new AdStrategyUc();

        // 代码位替换
        foreach ($info['ad_key'] ?? [] as $sk => $sv) {
            foreach ($sv['ad_list_beans'] ?? [] as $adk => $adv) {
                foreach ($Model->adType as $type) {
                    if (isset($adv['type']) && $adv['type'] == $type && !empty($params[$type])) {
                        $info['ad_key'][$sk]['ad_list_beans'][$adk]['gm_id'] = trim($params[$type]);
                    }
                }
            }
        }
        $params['info'] = json_encode($info, 320);
        $placement = [];
        foreach ($Model->adType as $type) {
            $placement[$type] = $params[$type] ?? '';
        }
        $params['placement'] = json_encode($placement);

        $Model->fill($params)->save();

        return $this->responseSuccess();
    }

    public function createName($app_name, $source, $launch_type, $key)
    {
        $source_name = '';
        foreach ($source as $row) {
            $source_name .= AppConst::SOURCE_NAME_MAP[$row] . ',';
        }
        $source_name = trim($source_name, ',');

        $launch_type_name = '';
        foreach ($launch_type as $row) {
            $launch_type_name .= AppConst::LAUNCH_TYPE_NAME_MAP[$row] . ',';
        }
        $launch_type_name = trim($launch_type_name, ',');

        $key_name = '';
        foreach ($key as $row) {
            $key_name .= AppConst::STRATEGY_KEY_NAME_MAP[$row] . ',';
        }
        $key_name = trim($key_name, ',');

        return $app_name . '-' . $source_name . '-' . $launch_type_name . '-' . $key_name;
    }
}