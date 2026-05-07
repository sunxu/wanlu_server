<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TempCommand extends Command
{
    protected $signature = 'temp:weather';

    protected $description = '临时脚本';

    public function handle()
    {
        $weather = DB::table('weather_guide')->orderBy('id')->get()->all();
        $data = [];
        $guide = '';
        $icon = '';
        foreach ($weather as $value){
            if(strpos($value['guide'], '1.') === 0){
                $guide = data_get($value, 'guide');
            }elseif($value['guide'] == '——'){
                $guide = '';
            }else{
                $guide .= '\n' . data_get($value, 'guide');
                $temp = array_pop($data);
                $temp['guide'] = $guide;
                $data[] = $temp;
            }

            if($value['icon_path']){
                $icon = '/weather_image/' .$value['icon_path'] . '.png';
            }

            if($value['alarm_name']){
                $levelCode = $value['level_code'];
                if(strpos($levelCode, '/')){
                    $levelCode = trim(substr($levelCode, strpos($levelCode, '/') + 1));
                }
                if($levelCode == 'III'){
                    $levelCode = 'Yellow';
                }
                if($levelCode == 'I'){
                    $levelCode = 'Orange';
                }
                if($levelCode == 'I'){
                    $levelCode = 'Red';
                }
                $data[] = [
                    'alarm_name' => $value['alarm_name'],
                    'type_code' => strtolower($value['type_code']),
                    'level_code' => strtolower($levelCode),
                    'icon_path' => $icon,
                    'guide' => trim($guide, '\n')
                ];
            }
        }
        DB::table('weather_alarm_guide')->insert($data);
        var_dump(json_encode($data));
    }
}