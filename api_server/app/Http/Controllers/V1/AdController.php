<?php
/**
 * 检测广告链接-广告通知
 */

namespace App\Http\Controllers\V1;

use App\Constants\AdConst;
use App\Services\Common\App;
use App\Services\Mkt\TencentClick;
use App\Services\Helper\Tools\Dding;
use DB;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller;
use Schema;

class AdController extends Controller
{
    public function tx(Request $request, $appCode)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $appCode) || !App::getAppByCode($appCode)) {
            return response()->json(['ret' => 1, 'msg' => '应用不存在'], 400);
        }
        try {
            $data = TencentClick::normalize($request->input());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ret' => 1, 'msg' => $e->getMessage()], 400);
        }
        $account = DB::connection('uc')->table('mkt_advertiser')->where('advertiser_id', $data['account_id'])->first();
        if (($account['platform'] ?? '') !== AdConst::PUT_SOURCE_TX || ($account['app_code'] ?? '') !== $appCode || empty($account['status'])) {
            return response()->json(['ret' => 1, 'msg' => '腾讯广告主未绑定该应用或已停用'], 400);
        }
        $table = App::tableAdTencent($appCode);
        if ($this->errorLog($appCode, $table, $request->input())) {
            return response()->json(['ret' => 1, 'msg' => '请先初始化腾讯点击表'], 503);
        }
        if (!DB::table($table)->where('account_id', $data['account_id'])->where('click_id', $data['click_id'])->exists()) {
            DB::table($table)->insert($data);
        }
        return response()->json(['ret' => 0, 'msg' => 'OK']);
    }

    public function jl(Request $request, $appCode)
    {
        // 请求参数
        $param = $request->input();

        if (empty($param)) {
            return response('FAILED', 500);
        }

        $app = App::getAppByCode($appCode);

        if (empty($app)) {
            return response('FAILED', 500);
        }

        $table = App::tableAdJuliang($appCode);
        if ($this->errorLog($appCode, $table, $param)) {
            return response('FAILED', 500);
        }

        // V3兼容处理
        if (!empty($param['project_id']) && $param['project_id'] !== '__PROJECT_ID__') {
            $param['campaign_id'] = $param['project_id'];
            $param['campaign_name'] = $param['project_name'] ?? '';
            $param['aid'] = $param['project_id'] . AdConst::PUT_PROJECT_AD_DELIMITER . ($param['ad_id'] ?? '');
            $param['aid_name'] = $param['ad_name'] ?? '';
        }

        DB::table($table)
            ->insert([
                'account_id' => $param['account_id'] ?? '',
                'imei' => $param['imei'] ?? '',
                'oaid' => $param['oaid'] ?? '',
                'android_id' => $param['android_id'] ?? '',
                'ip' => $param['ip'] ?? '',
                'ua' => urldecode($param['ua'] ?? ''),
                'model' => urldecode($param['model'] ?? ''),
                'mac' => $param['mac'] ?? '',
                'ts' => isset($param['ts']) ? intval($param['ts']) / 1000 : 0,// 毫秒转秒
                'os' => $param['os'] ?? '',
                'campaign_id' => $param['campaign_id'] ?? '',
                'campaign_name' => $param['campaign_name'] ?? '',
                'aid' => $param['aid'] ?? '',
                'aid_name' => $param['aid_name'] ?? '',
                'cid' => $param['cid'] ?? '',
                'cid_name' => $param['cid_name'] ?? '',
                'csite' => $param['csite'] ?? '',
                'convert_id' => $param['convert_id'] ?? '',
                'request_id' => $param['request_id'] ?? '',
                'union_site' => $param['union_site'] ?? '',
                'callback_url' => $param['callback_url'] ?? '',
                'create_time' => time(),
            ]);

        return 'SUCCESS';
    }

    public function ks(Request $request, $appCode)
    {
        // 请求参数
        $param = $request->input();

        if (empty($param)) {
            return response('FAILED', 500);
        }

        $app = App::getAppByCode($appCode);

        if (empty($app)) {
            return response('FAILED', 500);
        }

        $table = App::tableAdKuaishou($appCode);
        if ($this->errorLog($appCode, $table, $param)) {
            return response('FAILED', 500);
        }

        // 快手归因调整OAID为加密数据
        $param['oaid'] = !empty($param['oaid']) ? $param['oaid'] : ($param['oaid2'] ?? '');

        DB::table($table)
            ->insert([
                'imei' => $param['imei'] ?? '',
                'oaid' => $param['oaid'] ?? '',
                'android_id' => $param['android_id'] ?? '',
                'ip' => $param['ip'] ?? '',
                'ua' => urldecode($param['ua'] ?? ''),
                'model' => urldecode($param['model'] ?? ''),
                'mac' => $param['mac'] ?? '',
                'ts' => isset($param['ts']) ? intval($param['ts']) / 1000 : 0,// 毫秒转秒
                'os' => $param['os'] ?? '',
                'account_id' => $param['account_id'] ?? '',
                'campaign_id' => $param['aid'] ?? '',
                'aid' => $param['aid'] ?? '',// 跟巨量统一、存储广告主下级层级-广告组（系统内-对应计划）
                'aid_name' => ($param['did'] ?? '') . '_' . ($param['dname'] ?? ''),
                'cid' => $param['cid'] ?? '',
                'csite' => $param['csite'] ?? '',
                'callback_url' => $param['callback_url'] ?? '',
                'create_time' => time(),
            ]);

        return 'SUCCESS';
    }

    public function op(Request $request, $appCode)
    {
        // 请求参数
        $param = $request->input();

        if (empty($param)) {
            return response('FAILED', 500);
        }

        $app = App::getAppByCode($appCode);

        if (empty($app)) {
            return response('FAILED', 500);
        }

        $table = App::tableAdOppo($appCode);
        if ($this->errorLog($appCode, $table, $param)) {
            return response('FAILED', 500);
        }

        $imeiMd5 = data_get($param, 'imei_md5', '_IMEI_');
        $imei = data_get($param, 'imei', '$im$');

        $adId = data_get($param, 'adid', '__ADID__');
        $ad = data_get($param, 'ad', '$ad$');

        DB::table($table)
            ->insert([
                'imei' => (!empty($imeiMd5) && $imeiMd5 != '_IMEI_') ? $imeiMd5 : ((!empty($imei) && $imei != '$im$') ? $imei : ''),
                'oaid' => ($param['oaid'] ?? '') != '__OAID__' ? ($param['oaid'] ?? '') : '',
                'ip' => ($param['ip'] ?? '') != '__IP__' ? ($param['ip'] ?? '') : '',
                'ua' => ($param['ua'] ?? '') != '$ua$' ? ($param['ua'] ?? '') : '',
                'model' => ($param['m'] ?? '') != '$m$' ? ($param['m'] ?? '') : '',
                'ts' => ($param['ts'] ?? 0) > 0 ? ($param['ts'] ?? 0) / 1000 : time(),
                'android_id' => ($param['android_id'] ?? '') != '__ANDROIDID__' ? ($param['android_id'] ?? '') : '',
                'os' => ($param['os'] ?? '') != '$os$' ? ($param['os'] ?? '') : '',
                'os_version' => ($param['ov'] ?? '') != '$ov$' ? ($param['ov'] ?? '') : '',
                'account_id' => $param['account_id'] ?? '',
                'cid' => (!empty($adId) && $adId != '__ADID__') ? $adId : ((!empty($ad) && $ad != '$ad$') ? $ad : ''),
                'cid_name' => ($param['an'] ?? '') != '$an$' ? ($param['an'] ?? '') : '',
                'create_time' => time(),
            ]);

        return 'SUCCESS';
    }

    public function hw(Request $request, $appCode)
    {
        // 请求参数
        $param = $request->input();

        if (empty($param)) {
            return response('FAILED', 500);
        }

        $app = App::getAppByCode($appCode);

        if (empty($app)) {
            return response('FAILED', 500);
        }

        $table = App::tableAdHuawei($appCode);
        if ($this->errorLog($appCode, $table, $param)) {
            return response('FAILED', 500);
        }

        DB::table($table)
            ->insert([
                'account_id' => $param['corp_id'] ?? '',
                'imei' => $param['device_id'] ?? '',
                'oaid' => $param['oaid'] ?? '',
//                'android_id' => $param['device_id'] ?? '',
                'ip' => $param['ip'] ?? '',
                'ua' => urldecode($param['user_agent'] ?? ''),
                'ts' => $param['trace_time'] ?? 0,
                'campaign_id' => $param['adgroup_id'] ?? '',
                'campaign_name' => $param['adgroup_name'] ?? '',
                'aid' => $param['campaign_id'] ?? '',
                'aid_name' => $param['campaign_name'] ?? '',
                'cid' => $param['content_id'] ?? '',
                'cid_name' => $param['content_name'] ?? '',
                'transunique_id' => $param['transunique_id'] ?? '',
                'callback' => $param['callback'] ?? '',
                'create_time' => time(),
            ]);

        return 'SUCCESS';
    }

    public function vi(Request $request, $appCode)
    {
        // 请求参数
        $param = $request->input();

        if (empty($param)) {
            return response('FAILED', 500);
        }

        $app = App::getAppByCode($appCode);

        if (empty($app)) {
            return response('FAILED', 500);
        }

        $table = App::tableAdVivo($appCode);
        if ($this->errorLog($appCode, $table, $param)) {
            return response('FAILED', 500);
        }

        DB::table($table)
            ->insert([
                'account_id' => $param['account_id'] ?? '',
                'android_id' => !empty($param['android_id']) && $param['android_id'] != '__ANDROIDID__' ? $param['android_id'] : '',
                'imei' => !empty($param['imei']) && $param['imei'] != '__IMEI__' ? $param['imei'] : '',
                'oaid' => !empty($param['oaid']) && $param['oaid'] != '__OAIDPLAIN__' ? $param['oaid'] : '',
                'ip' => $param['ip'] ?? '',
                'ua' => !empty($param['ua']) && $param['ua'] != '__UA__' ? $param['ua'] : '',
                'ts' => intval($param['ts'] ?? 0) / 1000,
                'os' => $param['os'] ?? '',
                'os_version' => $param['ov'] ?? '',
                'model' => $param['model'] ?? '',
                'cid' => $param['cid'] ?? '',
                'request_id' => $param['request_id'] ?? '',
                'create_time' => time(),
            ]);

        return 'SUCCESS';
    }

    public function bd(Request $request, $appCode)
    {
        // 请求参数
        $param = $request->input();

        if (empty($param)) {
            return response('FAILED', 500);
        }

        $app = App::getAppByCode($appCode);

        if (empty($app)) {
            return response('FAILED', 500);
        }

        $table = App::tableAdBd($appCode);
        if ($this->errorLog($appCode, $table, $param)) {
            return response('FAILED', 500);
        }

        foreach ($param as $k => $v) {
            if ($v === 'NULL') {
                $param[$k] = '';
            }
        }

        DB::table($table)
            ->insert([
                'account_id' => $param['account_id'] ?? '',
                'imei' => $param['imei'] ?? '',
                'oaid' => $param['oaid'] ?? '',
                'android_id' => !empty($param['android_id']) && $param['android_id'] != '__ANDROIDID__' ? $param['android_id'] : '',
                'ip' => $param['ip'] ?? '',
                'ua' => $param['ua'] ?? '',
                'model' => $param['model'] ?? '',
                'ts' => intval($param['ts'] ?? 0) / 1000,
                'os' => $param['os'] ?? '',
                'os_version' => $param['os_version'] ?? '',
                'campaign_id' => $param['campaign_id'] ?? '',
                'aid' => $param['aid'] ?? '',
                'cid' => $param['cid'] ?? '',
                'click_id' => $param['click_id'] ?? '',
                'callback_url' => $param['callback_url'] ?? '',
                'create_time' => time(),
            ]);

        return 'SUCCESS';
    }

    public function honor(Request $request, $appCode)
    {
        // 请求参数
        $param = $request->input();

        if (empty($param)) {
            return response('FAILED', 500);
        }

        $app = App::getAppByCode($appCode);

        if (empty($app)) {
            return response('FAILED', 500);
        }

        $table = App::tableAdHonor($appCode);
        if ($this->errorLog($appCode, $table, $param)) {
            return response('FAILED', 500);
        }

        foreach ($param as $k => $v) {
            if ($v === 'NULL') {
                $param[$k] = '';
            }
        }

        DB::table($table)
            ->insert([
                'account_id' => $param['account_id'] ?? '',
                'oaid' => $param['oaid'] ?? '',
                'ip' => $param['ip'] ?? '',
                'ts' => time(),
                'os' => $param['os'] ?? '',
                'request_id' => $param['request_id'] ?? '',
                'track_id' => $param['track_id'] ?? '',
                'create_time' => time(),
            ]);

        return 'SUCCESS';
    }

    protected function errorLog(string $appCode, string $table, array $param)
    {
        if (!Schema::hasTable($table)) {
//            Dding::push('invalid table:' . $table);
            DB::table('com_ad_error')->insert([
                'app_code' => $appCode,
                'json' => json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);
            return true;
        }
        return false;
    }
}
