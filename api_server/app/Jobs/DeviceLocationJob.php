<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Lib\Amap\Amapi;
use App\Lib\IpSearch\IpSearch;
use DB;

class DeviceLocationJob extends BaseJob
{
    public function handle()
    {
        try {
            $datFilePath = resource_path('dats/qqzeng-ip-china-utf8.dat');
            $reader = new IpSearch($datFilePath);
            $area_info = $reader->get($this->data['ip']);
            list($continent,$country,$province, $city, $district,$isp,$areacode,$en,$cc,$lng,$lat) = explode("|", $area_info);
            $location = [
                'province' => $province,
                'city' => $city,
                'city_code' => $areacode,
                'way' => 'dat'
            ];
            if(!$city){
                throw new \Exception("not found city by ip");
            }
        }catch (\Exception $exception){
            $location = (new Amapi())->ip($this->data['ip']);
        }

        if (!empty($location)) {
            DB::table($this->data['table'])
                ->where('android_id', $this->data['android_id'])
                ->update([
                    'location' => json_encode($location, 320)
                ]);
        }
    }
}