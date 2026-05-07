<?php

namespace App\Services\Market;

use App\Services\JlMarket\KeepService as JlKeep;
use App\Services\KsMarket\KeepService as KsKeep;

class KeepFactory
{
    static private $instances = [];

    private function __construct()
    {
    }

    private function __clone()
    {

    }

    /**
     * 获取对应平台留存模型
     *
     * @param string $marketPlatform
     * @return KeepInterface
     */
    static public function instance(string $marketPlatform): KeepInterface
    {
        if(($keepService = data_get(self::$instances, $marketPlatform)) instanceof KeepInterface){
            return $keepService;
        }
        switch ($marketPlatform){
            case 'ks':
                $keepService = app(KsKeep::class);
                break;
            case 'jl':
                $keepService = app(JlKeep::class);
                break;
            default:

        }
        self::$instances[$marketPlatform] = $keepService;

        return $keepService;
    }
}