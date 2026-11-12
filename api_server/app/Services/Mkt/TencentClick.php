<?php

namespace App\Services\Mkt;

use InvalidArgumentException;

/** 将腾讯点击下发协议转换为现有点击表字段。 */
class TencentClick
{
    public static function normalize(array $params): array
    {
        foreach ($params as $value) {
            if (!is_scalar($value)) {
                throw new InvalidArgumentException('腾讯点击参数格式错误');
            }
        }
        if (strtolower((string)($params['app_type'] ?? 'android')) === 'ios') {
            throw new InvalidArgumentException('腾讯营销首期只支持 Android 应用');
        }
        foreach (['advertiser_id', 'adgroup_id'] as $key) {
            if (!ctype_digit((string)($params[$key] ?? '')) || (int)$params[$key] <= 0) {
                throw new InvalidArgumentException('腾讯点击缺少有效的账户或营销单元');
            }
        }
        $clickId = (string)($params['click_id'] ?? '');
        $timestamp = (string)($params['click_time'] ?? '');
        if ($clickId === '' || strlen($clickId) > 64 || !ctype_digit($timestamp)) {
            throw new InvalidArgumentException('腾讯点击标识或时间无效');
        }
        // 点击时间使用秒；兼容下发的毫秒值，禁止用接收时间替代缺失时间。
        $time = (int)$timestamp;
        if ($time > 9999999999) {
            $time = (int)floor($time / 1000);
        }
        if ($time <= 0 || $time > time() + 300) {
            throw new InvalidArgumentException('腾讯点击时间无效');
        }
        $hash = function ($value) {
            if ($value === '') {
                return '';
            }
            return preg_match('/^[a-f0-9]{32}$/iD', $value) ? strtolower($value) : md5($value);
        };
        $data = [
            'account_id' => (string)$params['advertiser_id'],
            'aid' => (string)$params['adgroup_id'],
            'campaign_id' => (string)($params['campaign_id'] ?? ''),
            'cid' => (string)($params['creative_id'] ?? ''),
            'click_id' => $clickId,
            'imei' => $hash(strtolower((string)($params['muid'] ?? ''))),
            'oaid' => (string)($params['oaid'] ?? ''),
            'android_id' => $hash((string)($params['android_id'] ?? '')),
            'ip' => (string)($params['ip'] ?? ''),
            'ua' => (string)($params['user_agent'] ?? ''),
            'ts' => $time,
            'os' => 0,
            'callback_url' => '',
            'create_time' => time(),
        ];
        if ($data['imei'] === '' && $data['oaid'] === '' && $data['android_id'] === '') {
            throw new InvalidArgumentException('腾讯点击缺少可归因的设备标识');
        }
        // 与复制的百度点击表现有字段长度保持一致，不截断设备标识。
        foreach (['account_id' => 100, 'aid' => 100, 'campaign_id' => 100, 'cid' => 100, 'oaid' => 100, 'ip' => 50, 'ua' => 255] as $key => $length) {
            if (strlen($data[$key]) > $length) {
                throw new InvalidArgumentException('腾讯点击字段过长：' . $key);
            }
        }
        return $data;
    }
}
