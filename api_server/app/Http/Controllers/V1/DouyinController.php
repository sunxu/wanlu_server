<?php

namespace App\Http\Controllers\V1;

use App\Lib\Media\CSJCPS;
use App\Services\Helper\Helper;
use Cache;
use Illuminate\Http\Request;

class DouyinController
{
    public function link(Request $request)
    {
        $params = $request->get('_param');

        $appCode = $params['_app']['appcode'];
        $mediaAppId = $params['_app']['media_conf']['csj'] ?? '';
        $mediaSecret = $params['_app']['media_conf_csj']['secret'] ?? '';

        if (empty($mediaAppId) || empty($mediaSecret)) {
            return Helper::response(2001, [], '应用媒体配置异常');
        }

        $key = $appCode . '-douyin-link';
        $link = Cache::get($key);

        if (empty($link)) {
            try {
                $link = (new CSJCPS($mediaAppId, $mediaSecret))->link();
                if (!empty($link)) {
                    $link = array_merge($link, [
                        'image_url' => 'https://product-public-nls.oss-cn-beijing.aliyuncs.com/ad_image/douyin.png',
                        'title' => '百亿补贴，限时抢购',
                        'desc' => '百亿补贴等你来购',
                        'clickBtnTxt' => '立即前往'
                    ]);
                    Cache::put($key, $link, 3600);
                }
            } catch (\Exception $e) {
                return Helper::response(2002, [], $e->getMessage());
            }
        }
        return Helper::response(0, $link ?? []);
    }
}