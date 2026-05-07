<?php

declare(strict_types=1);

namespace App\Service\Mkt;

use App\Model\Mkt\MktAlertDelegate;
use App\Utils\Ding;

class AlertDelegateService
{
    /**
     * 创建代投配置
     */
    public function createDelegate(array $data): MktAlertDelegate
    {
        $delegate = new MktAlertDelegate();
        $delegate->fill($data)->save();
        return $delegate;
    }

    /**
     * 配置列表
     */
    public function delegateList(array $params)
    {
        $query = MktAlertDelegate::query()
            ->where('deleted_time', 0)
            ->orderBy('created_time', 'desc');

        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['delegate_name'])) {
            $query->where('delegate_name', 'like', '%' . $params['delegate_name'] . '%');
        }

        return $query->paginate((int)($params['per_page'] ?? 10));
    }

    /**
     * 配置详情
     */
    public function delegateInfo(int $delegateId): ?MktAlertDelegate
    {
        return MktAlertDelegate::query()
            ->where('delegate_id', $delegateId)
            ->where('deleted_time', 0)
            ->first();
    }

    /**
     * 更新配置
     */
    public function updateDelegate(int $delegateId, array $data): bool
    {
        $delegate = MktAlertDelegate::query()
            ->where('delegate_id', $delegateId)
            ->where('deleted_time', 0)
            ->first();

        if (!$delegate) {
            throw new \Exception('配置不存在');
        }

        $delegate->fill($data)->save();
        return true;
    }

    /**
     * 删除配置
     */
    public function deleteDelegate(int $delegateId): bool
    {
        $delegate = MktAlertDelegate::query()
            ->where('delegate_id', $delegateId)
            ->where('deleted_time', 0)
            ->first();

        if (!$delegate) {
            throw new \Exception('配置不存在');
        }

        $delegate->deleted_time = time();
        return $delegate->save();
    }

    /**
     * 测试钉钉消息
     */
    public function testDing(int $delegateId): bool
    {
        $delegate = $this->delegateInfo($delegateId);
        if (!$delegate) {
            throw new \Exception('配置不存在');
        }

        $ding = new Ding();
        $ding->setDingConfig($delegate->ding_token, $delegate->ding_secret);

        $message = "### 测试消息\n\n这是一条测试消息，代投配置验证成功！\n\n发送时间：" . date('Y-m-d H:i:s');

        return $ding->pushMarkdown('报警规则测试', $message);
    }
}
