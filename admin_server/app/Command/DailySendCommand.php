<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Placement\LaunchBaseService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use App\Service\Mkt\MktService;


/**
 * @Command
 */
#[Command]
class DailySendCommand extends HyperfCommand
{
    protected $name = 'daily:send';

    
    public function handle()
    {
        //$list = Db::table('report_daily_ad')->where('date','2023-06-01')->where('active_cost',">",5)->pluck('active_cost','ad_id')->toArray();
        $list = Db::select("select ad_id,active_cost from report_daily_ad 

where date = '2023-06-01' and active_cost > 4 ORDER BY updated_time desc");
        var_dump($list);
        file_put_contents('ad.txt',json_encode($list));
        $num = 0;
        foreach($list as $v){
            var_dump($v->ad_id);
            make(MktService::class)->adUpdateStatus($v->ad_id, 'disable');
            $num++;
            if($num == 20){
                $num = 0;
                sleep(20);
            }
        }
        
    }
}
