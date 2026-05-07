<?php

namespace App\Services\Pay;

use Illuminate\Support\Facades\DB;

class VIPService
{
    public function grantVip($appCode, $tradeNo)
    {
        $tradeInfo = DB::table("app_{$appCode}_trade")->where('trade_no', $tradeNo)->first();
        $goodsInfo = DB::table("app_paid_goods")->where('goods_no', data_get($tradeInfo, 'goods_no'))->first();
        $vipInfo = DB::table("app_{$appCode}_vip")->where('account', data_get($tradeInfo, 'account'))->first();
        if($vipInfo){
            $expiredTime = data_get($vipInfo, 'expired_time');
        }else{
            $expiredTime = 0;
        }
        $sku = data_get($goodsInfo, 'goods_sku');
        $sku = json_decode($sku, true);
        $value = data_get($sku, 'count_value');
        switch (data_get($sku, 'use_type')){
            case 'day':
                $goodsTime = 60 * 60 * 24 * $value;
                break;
            case 'week':
                $goodsTime = 60 * 60 * 24 * 7 * $value;
                break;
            case 'month':
                $goodsTime = 60 * 60 * 24 * 30 * $value;
                break;
            case 'year':
                $goodsTime = 60 * 60 * 24 * 365 * $value;
            default:
                $goodsTime = 0;
        }
        if($expiredTime > time()){
            $resultTime = $expiredTime + $goodsTime;
        }else{
            $resultTime = strtotime(date('Y-m-d 00:00:00')) + $goodsTime - 1;
        }
        if($vipInfo){
            DB::table("app_{$appCode}_vip")->where('account', data_get($tradeInfo, 'account'))->update(['expired_time' => $resultTime, 'updated_time' => time()]);
        }else{
            DB::table("app_{$appCode}_vip")->insert(['expired_time' => $resultTime, 'updated_time' => time(), 'account' => data_get($tradeInfo, 'account'), 'created_time' => time()]);
        }
        DB::table("app_{$appCode}_vip_history")->insert([
            'account' => data_get($tradeInfo, 'account'),
            'pre_time' => $expiredTime,
            'buy_time' => $resultTime,
            'trade_no' => $tradeNo,
            'goods_sku' => data_get($goodsInfo, 'goods_sku'),
            'created_time' => time()
        ]);
    }

    /**
     * 获取vip信息
     * @param $appCode
     * @param $account
     * @return array
     */
    public function getVIPInfo($appCode, $account)
    {
        $vipInfo = DB::table("app_{$appCode}_vip")->where('account', $account)->first();
        if (!$vipInfo){
            return [
                'expired_time' => 0,
                'is_vip' => false,
            ];
        }
        return [
            'expired_time' => data_get($vipInfo, 'expired_time'),
            'is_vip' => data_get($vipInfo, 'expired_time') > time(),
        ];
    }
}