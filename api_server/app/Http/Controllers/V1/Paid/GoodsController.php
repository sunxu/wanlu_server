<?php

namespace App\Http\Controllers\V1\Paid;

use App\Http\Controllers\V1\AppBaseController;
use App\Services\Helper\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function config;
use function data_get;

class GoodsController extends AppBaseController
{
    public function getGoods(Request $request)
    {
        $appCode = data_get($request->get('_param'), '_app.appcode');
        $payChannel = config("appconf.app.{$appCode}.payChannel", []);
        $goods = DB::table('app_paid_goods')
            ->where('is_enable', 1)
            ->select(['goods_no', 'goods_name', 'goods_price', 'discounts_goods', 'is_discounts'])
            ->orderBy('is_discounts', 'desc')
            ->orderBy('goods_price')
            ->orderBy('discounts_goods')
            ->get()->map(function ($item){
                $item['goods_price'] = intval(data_get($item, 'goods_price'));
                $item['discounts_goods'] = intval(data_get($item, 'discounts_goods'));
                return $item;
            })->all();

        return Helper::response(0, ['channel' => $payChannel, 'goods' => $goods]);
    }
}