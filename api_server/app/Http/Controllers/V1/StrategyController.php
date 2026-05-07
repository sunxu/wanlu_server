<?php
/**
 * 策略测试专用
 */

namespace App\Http\Controllers\V1;

use App\Services\Helper\Helper;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller;

class StrategyController extends Controller
{
    public function test($appCode)
    {
        return view('strategy.test', [
            'appId' => $appCode,
            'appName' => DB::connection('uc')->table('app')->where('app_code', $appCode)->value('app_name')
        ]);
    }

    /**
     * 策略页面
     * @param $appCode
     * @return JsonResponse
     */
    public function info($appCode)
    {
        $versionList = DB::table('app_version')
            ->select([
                'id',
                'version',
                'test_status',
                'audit_status'
            ])
            ->where('app_code', $appCode)
            ->get()
            ->toArray();
        foreach ($versionList as &$v) {
            $v['audit_status'] = empty($v['audit_status']) ? [] : json_decode($v['audit_status'], true);
        }
        unset($v);
        return Helper::response(0, [
            'versionList' => $versionList
        ]);
    }

    /**
     * 策略信息版本
     * @param Request $request
     * @param $appCode
     * @return JsonResponse
     */
    public function save(Request $request, $appCode)
    {
        $param = $request->input();

        $result = false;
        if (isset($param['versionId']) && isset($param['test']) && isset($param['version'])) {
            $result = DB::table('app_version')
                ->where('app_code', $appCode)
                ->where('id', $param['versionId'])
                ->update([
                    'version' => trim($param['version']),
                    'test_status' => intval($param['test']),
                    'update_time' => time()
                ]);
        }
        return Helper::response($result ? 0 : 1000);
    }
}
