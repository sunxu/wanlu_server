<?php

namespace App\Http\Controllers\V1\Paid;

use App\Jobs\NotifyAdPayJob;
use App\Services\Pay\VIPService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Formatter;

class CallbackController extends Controller
{
    public function __construct(Request $request)
    {
        Log::channel('callback')->info('callback', [
            'params' => $request->getContent(), 'header' => $request->headers, 'url' => $request->url(),
        ]);
    }
    public function wechat(Request $request, $appCode)
    {
        $inWechatpaySignature = $request->header('Wechatpay-Signature');// 请根据实际情况获取
        $inWechatpayTimestamp = $request->header('Wechatpay-Timestamp');// 请根据实际情况获取
        $inWechatpaySerial = $request->header('Wechatpay-Serial');// 请根据实际情况获取
        $inWechatpayNonce = $request->header('Wechatpay-Nonce');// 请根据实际情况获取
        $requestId = $request->header('Request-ID');// 请根据实际情况获取
        $inBody = $request->getContent();// 请根据实际情况获取，例如: file_get_contents('php://input');
        $merchantId = config("appconf.app.{$appCode}.mchid", '');
        $apiv3Key = config("wechatpay.mchid.{$merchantId}.apikey", '');
        $certificateFile = config("wechatpay.mchid.{$merchantId}.certificateFile", '');

        // 根据通知的平台证书序列号，查询本地平台证书文件，
// 假定为 `/path/to/wechatpay/inWechatpaySerial.pem`
        $platformPublicKeyInstance = Rsa::from("file://{$certificateFile}", Rsa::KEY_TYPE_PUBLIC);

        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        if ($timeOffsetStatus && $verifiedStatus) {
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray = (array)json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext' => $ciphertext,
                'nonce' => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $apiv3Key, $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = (array)json_decode($inBodyResource, true);
            Log::channel('callback')->info('callback', [
                'params' => $inBodyResourceArray,
                'requestId' => $requestId,
            ]);
            try {
                $this->process($inBodyResourceArray);
            }catch (\Exception $exception){
                Log::channel('callback')->error('callback process error', ['message'=>$exception->getMessage(), 'file' => $exception->getFile(), 'line'=> $exception->getLine()]);
            }
        }
    }

    public function process(array $wechatData)
    {
        if (strtoupper(data_get($wechatData, 'trade_type')) === 'APP'){
            $tradeNo = data_get($wechatData, 'out_trade_no');
            $tradeNoInfo = explode('-', $tradeNo);
            $appCode = array_shift($tradeNoInfo);
            $tradeStatus = DB::table("app_{$appCode}_trade")->where('trade_no', $tradeNo)->select('trade_status')->pluck('trade_status')->first();
            if($tradeStatus != 0){
                return;
            }
            $status = strtoupper(data_get($wechatData, 'trade_state'));
            switch ($status){
                case 'SUCCESS': $tradeStatus = 1; break;
                case 'REFUND': $tradeStatus = 2; break;
                case 'NOTPAY':
                case 'CLOSED':
                case 'REVOKED':
                case 'USERPAYING': $tradeStatus = 4; break;
                default: $tradeStatus = 3;
            }
            DB::table("app_{$appCode}_trade")->where('trade_no', $tradeNo)->update(['trade_status' => $tradeStatus, 'updated_time' => time(), 'attach' => json_encode($wechatData)]);
            if($tradeStatus !== 1){
                return;
            }
            app(VIPService::class)->grantVip($appCode, $tradeNo);

            dispatch((new NotifyAdPayJob([
                'app_code' => $appCode,
                'trade_no' => $tradeNo,
            ])));
        }
    }
}