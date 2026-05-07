<?php
/**
 * 认证中间件
 */

namespace App\Http\Middleware;

use App\Services\Common\App;
use App\Services\Helper\Encrypt\Aes;
use App\Services\Helper\Helper;
use App\Services\Helper\Tools\Dding;
use Closure;
use Illuminate\Http\Request;
use Log;
use Schema;

class ApiAuth
{
    /**
     * 加密秘钥
     * @var
     */
    private $aesKey;

    /**
     * 签名秘钥
     * @var
     */
    private $md5key;

    /**
     * 日志黑名单
     * @var array
     */
    private $logBlacklist = [
        'v1/event/ad',
        'v1/event/active',
        'v1/event/dialog',
        'v1/event/information',
    ];

    /**
     * Handle an incoming request.
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // 权限校验
        $result = $this->auth($request);
        // 验证失败
        if ($result['code'] !== 0) {
            if ($result['code'] < 1100) {
                Log::warning('ApiAuth:handle', [
                    'id' => 1,
                    'path' => $request->path(),
                    'param' => $request->input(),
                    'result' => $result
                ]);
            }
            // 输出响应
            return Helper::response($result['code'], $result['data'], $result['msg']);
        }
        // 响应内容
        $response = $next($request);
        // 业务响应
        $bizData = $response->getContent();
        // 转换格式
        $bizData = json_decode($bizData, true);
        // 日志
        if (env('APP_ENV') != 'production') {
            // 日志
            Log::info('ApiAuth:handle', [
                'id' => 2,
                'path' => $request->path(),
                'param' => $request->get('_param', []),
                'result' => $bizData
            ]);
        }
        // 数据验证
        if (!isset($bizData['code'])) {
            // 返回
            return Helper::response(1000);
        }
        // 业务异常
        if ($bizData['code'] !== 0 || empty($bizData['data'])) {
            return $response;
        }
        $data = [
            'response' => Aes::encrypt(json_encode($bizData['data'], 320), $this->aesKey)
        ];
        // 输出响应
        return Helper::response($bizData['code'], $data, $bizData['msg']);
    }

    /**
     * 检查权限
     * @param Request $request
     * @return array
     */
    private function auth(Request $request): array
    {
        // 请求参数
        $param = $request->input();
        // 验证应用及签名
        if (empty($param['appid']) || empty($param['timestamp']) || empty($param['version']) || empty($param['data']) || empty($param['sign'])) {
            return Helper::return(1001);
        }
        // 验证有效期|24小时
        if (abs(intval($param['timestamp']) - time()) > 24 * 3600) {
            return Helper::return(1002);
        }
        // 应用信息
        $app = App::getAppById($param['appid']);
        if (empty($app) || !$app['status'] || empty($app['appkey'])) {
            return Helper::return(1101, [], ($app['appname'] ?? '') . '应用异常');
        }
        if (!App::hasApp($app['appcode'])) {
            Dding::push($app['appname'] . '：应用表异常');
            return Helper::return(1102, [], '应用表异常');
        }
        // 加密秘钥|前16位
        $this->aesKey = substr($app['appkey'], 0, 16);
        // 签名秘钥
        $this->md5key = substr($app['appkey'], -16);
        // 加号被转义还原
        // $param['data'] = str_replace(' ', '+', $param['data']);
        // 验证秘钥
        if (!$this->checkSign($param, $this->md5key)) {
            return Helper::return(1003);
        }
        $data = Aes::decrypt($param['data'], $this->aesKey);
        // 解密失败
        if ($data === false) {
            return Helper::return(1005);
        }
        // 转换格式
        $param['data'] = json_decode($data, true);
        if (!is_array($param['data'])) {
            return Helper::return(1001, [], '业务参数格式错误');
        }
        // 参数|IP地址
        unset($param['data']['ip']);
        // 加参数|APP信息
        $param['_app'] = $app;
        // 参数传递
        $request->attributes->add(['_param' => $param]);
        // 校验成功
        return Helper::return(0);
    }

    /**
     * 获取签名
     * @param array $param
     * @param $key
     * @return string
     */
    private function getSign(array $param, $key)
    {
        // 过滤签名
        unset($param['sign']);
        // 过滤空值
        $param = array_filter($param, 'strlen');
        // 排序
        ksort($param);
        // 追加秘钥
        $param['key'] = $key;
        // 生成签名
        return strtoupper(md5(urldecode(http_build_query($param))));
    }

    /**
     * 验证签名
     * @param array $param
     * @param $key
     * @return bool
     */
    private function checkSign(array $param, $key)
    {
        if (empty($key) || empty($param['sign'])) {
            return false;
        }
        // 待验证签名
        $sign = $param['sign'] ?? '';
        // 返回
        return ($sign === $this->getSign($param, $key));
    }
}
