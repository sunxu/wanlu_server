<?php

namespace App\Jobs;

use App\Services\Pay\VIPService;
use App\Services\Pay\WechatService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WechatTradeJob extends BaseJob
{
    /**
     * 任务可以尝试的最大次数。
     *
     * @var int
     */
    public $tries = 3;

    /**
     * 任务可以执行的最大秒数 (超时时间)。
     *
     * @var int
     */
    public $timeout = 60;

    protected $tradeNo;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(string $tradeNo)
    {
        $this->tradeNo = $tradeNo;
        parent::__construct($tradeNo);
        Log::info('wechat trade job', [
            'tradeNo'=> $tradeNo
        ]);
    }

    public function tags()
    {
        return [
            'wechat_trade'
        ];
    }

    /**
     * @throws \Throwable
     */
    public function handle()
    {
        $tradeNoInfo = explode('-', $this->tradeNo);
        $appCode = array_shift($tradeNoInfo);
        $tradeStatus = DB::table("app_{$appCode}_trade")->where('trade_no', $this->tradeNo)->select('trade_status')->pluck('trade_status')->first();
        if($tradeStatus != 0){
            return;
        }

        $merchantId = config("appconf.app.{$appCode}.mchid", '');
        $config = config("wechatpay.mchid.{$merchantId}", []);
        $response = app(WechatService::class)->requestWechat($config, 'get', "/v3/pay/transactions/out-trade-no/{$this->tradeNo}?mchid={$merchantId}");
        $status = strtoupper(data_get($response, 'trade_state'));
        switch ($status){
            case 'SUCCESS': $tradeStatus = 1; break;
            case 'REFUND': $tradeStatus = 2; break;
            case 'NOTPAY':
            case 'CLOSED':
            case 'REVOKED':
            case 'USERPAYING': $tradeStatus = 4; break;
            default: $tradeStatus = 3;
        }
        DB::table("app_{$appCode}_trade")->where('trade_no', $this->tradeNo)->update(['trade_status' => $tradeStatus, 'updated_time' => time(), 'attach' => json_encode($response)]);
        if($tradeStatus !== 1){
            return;
        }
        app(VIPService::class)->grantVip($appCode, $this->tradeNo);

        dispatch((new NotifyAdPayJob([
            'app_code' => $appCode,
            'trade_no' => $this->tradeNo,
        ])));
    }
}
