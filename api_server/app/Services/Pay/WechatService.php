<?php

namespace App\Services\Pay;

use App\Jobs\WechatTradeJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;
use WeChatPay\Formatter;

class WechatService
{
    public function getPrepayId(string $appCode, $androidId, $goods_no)
    {
        $merchantId = config("appconf.app.{$appCode}.mchid", '');
        $appId = config("appconf.app.{$appCode}.wechatAppId", '');
        $config = config("wechatpay.mchid.{$merchantId}", []);
        $tradeNo = uniqid($appCode . '-' . date("YmdHis"));
        $goodsInfo = DB::table('app_paid_goods')->where('goods_no', $goods_no)->first();
        $amount = data_get($goodsInfo, 'goods_price');
        if(data_get($goodsInfo, 'is_discounts') == 1){
            $amount = data_get($goodsInfo, 'discounts_goods');
        }
        DB::table("app_{$appCode}_trade")->insert([
            'trade_no' => $tradeNo,
            'goods_no' => $goods_no,
            'account' => $androidId,
            'amount' => $amount,
            'channel' => 1,
            'created_time' => time(),
            'updated_time' => time(),
        ]);

        $tradeExpireTime = time() + 5 * 60;
        $data = [
            'mchid' => $merchantId,
            'appid' => $appId,
            'description' => '会员付费服务-'.$goods_no,
            'out_trade_no' => $tradeNo,
            'time_expire' => date('Y-m-d', $tradeExpireTime - 5) . 'T' .date('H:i:s+08:00', $tradeExpireTime - 5),
            'notify_url' => config('wechatpay.callback_domain') . "/v1/wechat/callback/{$appCode}",
            'amount' => [
                'total' => intval($amount),
                'currency' => 'CNY'
            ]
        ];
        $res = $this->requestWechat($config, 'post', '/v3/pay/transactions/app', $data);
        dispatch((new WechatTradeJob($tradeNo))->delay(5 * 60));
        $merchantPrivateKeyFilePath = 'file://' . $config['privateKeyFile'];
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath);

        $params = [
            'appid'     => $appId,
            'timestamp' => (string)Formatter::timestamp(),
            'noncestr'  => Formatter::nonce(),
            'prepayid'   => data_get($res, 'prepay_id'),
        ];
        $params += ['paySign' => Rsa::sign(
            Formatter::joinedByLineFeed(...array_values($params)),
            $merchantPrivateKeyInstance
        ), 'signType' => 'RSA', 'packageValue' => "Sign=WXPay", 'partnerId' => $merchantId];

        return $params;
    }

    public function requestWechat(array $config, string $method, string $uri, array $data = [])
    {
//         从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
        $merchantPrivateKeyFilePath = 'file://' . $config['privateKeyFile'];
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);

        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
        $platformCertificateFilePath = 'file://' . $config['certificateFile'];
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 从「微信支付平台证书」中获取「证书序列号」
        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
        $instance = Builder::factory([
            'mchid'      => $config['mchid'],
            'serial'     => $config['serial'],
            'privateKey' => $merchantPrivateKeyInstance,
            'certs'      => [
                $platformCertificateSerial => $platformPublicKeyInstance,
            ],
        ]);
        try {
            switch ($method){
                case 'post':
                    $resp = $instance->chain($uri)->post(['json' => $data]);
                    break;
                case 'get':
                    $resp = $instance->chain($uri)->get($data);
                    break;
                default:
                    throw new \Exception("不支持的请求类型【{$method}】");

            }

        }catch (\Exception $exception){
            Log::error('wechatpay error', [
                'uri' => $uri, 'params' => $data, 'msg' => $exception->getMessage(), 'status' => $exception->getCode(),
                'file' => $exception->getFile(), 'line' => $exception->getLine(), 'config' => $config, 'method' => $method
                ]);
            throw $exception;
        }
        $res = json_decode($resp->getBody()->getContents(), true);
        Log::info('wechatpay error', [
            'uri' => $uri, 'params' => $data,  'config' => $config, 'method' => $method, 'res' => $res
        ]);
        return $res;
    }
}