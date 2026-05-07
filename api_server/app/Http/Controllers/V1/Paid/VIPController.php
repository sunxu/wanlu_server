<?php

namespace App\Http\Controllers\V1\Paid;

use App\Http\Controllers\V1\AppBaseController;
use App\Services\Helper\Helper;
use App\Services\Pay\VIPService;
use App\Services\Pay\WechatService;
use Illuminate\Http\Request;

class VIPController extends AppBaseController
{
    protected $vipService;

    public function __construct(Request $request, VIPService $vipService)
    {
        parent::__construct($request);
        $this->vipService = $vipService;
    }

    public function getVIPInfo(Request $request)
    {
        $params = data_get($request->get('_param'), 'data');
        $appCode = data_get($request->get('_param'), '_app.appcode');
        $androidId = data_get($params, 'android_id');
        $result = $this->vipService->getVIPInfo($appCode, $androidId);

        return Helper::response(0, $result);
    }
}