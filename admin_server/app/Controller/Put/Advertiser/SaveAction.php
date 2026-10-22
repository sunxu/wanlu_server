<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Controller\Put\Advertiser;

use App\Constants\ErrorCode;
use App\Constants\MktConst;
use App\Constants\RedisConst;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Model\Mkt\MktAdvertiser as Advertiser;
use App\Utils\ValidateTrait;

class SaveAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'advertiser_name' => ['required'],//广告主名称
            'advertiser_id' => ['required'],//广告主名称
            'launch_type' => ['required'],//投放方式
            'action_type' => ['required'],//关键行为[0]不触发[1]回收率%[2]回收金额[3]网赚关键行为
            'action_revenue' => ['nullable'],//关键行为:回收率、回收金额
            'action_value' => ['nullable'],//网赚关键行为定义
            'active_type' => ['required'],//激活定义[1]开屏[2]归因[3]回收[4]激励视频
            'active_value' => ['nullable'],//激活回收标准值
            'keep_type' => ['required'],
            'notify_fill' => ['required'],
            'notify_discount' => ['nullable'],
            'extra' => ['nullable'],
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());
        $Model = Advertiser::find($params['advertiser_id']);
        if (!$Model) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '数据不存在');
        }

        $Model->advertiser_name = $params['advertiser_name'];
        $Model->launch_type = $params['launch_type'];
        $Model->action_type = $params['action_type'];
        $Model->action_revenue = $params['action_revenue'];
        $Model->action_value = isset($params['action_value']) ? json_encode(json_decode($params['action_value'], true) ?: [], 320) : null;
        $Model->active_type = $params['active_type'];
        $Model->active_value = isset($params['active_value']) ? json_encode(json_decode($params['active_value'], true) ?: [], 320) : null;
        $Model->keep_type = $params['keep_type'];
        $Model->notify_fill = $params['notify_fill'];
        $Model->notify_discount = $params['notify_discount'] ?? 0;
        if ($Model->platform === MktConst::PLATFORM_TX) {
            if (isset($params['extra']) && trim($params['extra']) !== '') {
                $extra = json_decode($params['extra'], true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($extra)) {
                    throw new ValidationException(ErrorCode::PARAMS_ERROR, '扩展配置必须是有效的 JSON 对象');
                }
                $Model->extra = array_replace($Model->extra ?? [], $extra);
            }
        } else {
            $Model->extra = isset($params['extra']) ? json_decode($params['extra'], true) : [];
        }
        $Model->save();

        /*删除缓存数据*/
        $Redis = redis_client(RedisConst::POOL_NAME_MIS_AD);
        $cache_key = sprintf(RedisConst::MODEL_CACHE_KEY, $Model->getTable(), $Model->advertiser_id);
        $Redis->del($cache_key);

        return $this->responseSuccess();
    }
}