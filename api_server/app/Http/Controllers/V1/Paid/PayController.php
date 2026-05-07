<?php

namespace App\Http\Controllers\V1\Paid;

use App\Http\Controllers\V1\AppBaseController;
use App\Services\Helper\Helper;
use App\Services\Pay\WechatService;
use Illuminate\Http\Request;

class PayController extends AppBaseController
{
    protected $wechatService;

    public function __construct(Request $request, WechatService $wechatService)
    {
        parent::__construct($request);
        $this->wechatService = $wechatService;
    }

    public function getWechatPrepayId(Request $request)
    {
        $params = data_get($request->get('_param'), 'data');
        $appCode = data_get($request->get('_param'), '_app.appcode');
        $goods_no = data_get($params, 'goods_no');
        $androidId = data_get($params, 'android_id');
        $result = $this->wechatService->getPrepayId($appCode, $androidId, $goods_no);

        return Helper::response(0, $result);
    }
}