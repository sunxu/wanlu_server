<?php

namespace App\Http\Controllers\V1;

use App\Services\Helper\Helper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FeedbackController extends AppBaseController
{
    /**
     * @return JsonResponse
     */
    public function submit()
    {
        if (strlen($this->params['contact']) == 0 || strlen($this->params['content']) == 0) {
            return Helper::response(1001, ['contact', 'content'], '参数错误');
        }

        $result = DB::connection('uc')->table('app_feedback')
            ->insert([
                'app_code' => $this->app['appcode'],
                'contact' => $this->params['contact'],
                'content' => $this->params['content'],
                'created_time' => time(),
                'account' => $this->params['account']
            ]);
        return Helper::response($result ? 0 : 1000);
    }
}