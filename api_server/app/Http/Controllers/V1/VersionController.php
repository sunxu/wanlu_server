<?php
/**
 * APP版本
 */

namespace App\Http\Controllers\V1;

use App\Constants\AdConst;
use App\Services\Common\Account;
use App\Services\Common\App;
use App\Services\Helper\Helper;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

class VersionController extends AppBaseController
{
    /**
     * 联运配置信息
     * @return JsonResponse
     */
    public function config()
    {
        $source = AdConst::PUT_SOURCE_LY;
        $list = DB::connection('uc')->table('ad_strategy')
            ->where('app_code', $this->app['appcode'])
            ->where('source', 'like', '%' . $source . '%')
            ->where('key', 'like', "%common%")
            ->select(['id', 'info'])
            ->get()
            ->toArray();

        // 策略数
        $num = count($list) > 0 ? count($list) : 1;
        $randomGroupId = $this->params['random_group_id'] ?? 0;
        $id = $randomGroupId % 20;
        $d = round(20 / $num);
        for ($i = 0; $i < $num; $i++) {
            if ($id >= $i * $d && $id < ($i + 1) * $d) {
                break;
            }
        }

        $strategy = $list[min($i, $num - 1)] ?? [];
        $v = empty($strategy['info']) ? [
            'ad_key' => [['_' => '_']],
            'config' => ['keepalive' => true],
            'strategy' => [
                'key' => 'audit'
            ],
        ] : json_decode($strategy['info'], true);
        $v['strategy']['v'] = $i;
        $v['strategy']['key'] = 'common';// 客户端要求补全

        // 暂时不过滤黑名单
//        // 黑名单策略
//        $black = App::isProduct() && $this->black();
//        // 黑名单过滤
//        if ($this->isAudit() || $black) {
//            $v['strategy']['key'] = 'audit';// 客户端要求补全
//            $v['ad_key'] = [['_' => '_']];
//              $target = 'black';
//        }

        if ($this->location()) {
            $v['strategy']['key'] = 'audit';// 客户端要求补全
            $v['ad_key'] = [['_' => '_']];
            $target = 'location';
        }

        $data = [
            'version' => $this->version,
            'req_type' => $this->params['from'] ?? '',
            'channel' => $this->channel,
            'imei' => json_encode([
                'id' => $strategy['id'] ?? 0,
                'source' => $source,
            ]),
            'oaid' => $this->params['oaid'] ?? '',
            'android_id' => $this->params['androidid'] ?? '',
            'vendor' => $this->params['vendor'] ?? '',
            'model' => $this->params['model'] ?? '',
            'strategy_version' => $randomGroupId . ':' . $id,
            'strategy_channel' => $v['strategy']['v'],
            'strategy_key' => $v['strategy']['key'] ?? '',
            'strategy_target' => $target ?? '',
            'create_time' => time()
        ];
        Redis::rpush(App::logListKey('strategy', $this->app['appcode']), json_encode($data, 320));

        return Helper::response(0, $v);
    }

    /**
     * 联运黑名单
     * @return bool
     */
    public function black()
    {
        if (($this->params['app_debug'] ?? false) && ($this->params['is_adb'] ?? false)) {
            $this->insertBlack([
                'app_debug' => true,
                'is_adb' => true
            ]);
            return true;
        }
        return false;
    }
}
