<?php

namespace App\Jobs;

use App\Constants\MarketConst;
use App\Services\Common\App;
use App\Services\Market\KeepFactory;
use Illuminate\Support\Facades\DB;

class KeepJob extends BaseJob
{
    protected $platform = [
        ''
    ];
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = data_get($this->data, 'data', []);
        $appCode = data_get($this->data, 'appCode', '');
        foreach ( $data as $value){
            $params = json_decode($value, true);
            $androidId = data_get($params, 'android_id');
            $source = DB::table(App::tableDevice($appCode))->where('android_id', $androidId)->pluck('source')->first();
            if( $source == MarketConst::PLATFORM['jl']){
                KeepFactory::instance('jl')->doKeep($appCode, $params);
            }elseif ( $appCode == MarketConst::PLATFORM['ks']){
                KeepFactory::instance('ks')->doKeep($appCode, $params);
            }
        }
    }
}