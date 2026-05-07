<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Library\HttpClient\HttpClient;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class UmengEventPv extends HyperfCommand
{
    protected $name = 'temp:umengeventpv';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本");
    }

    protected $beginTime = '2023-03-03 00:00:00';
    protected $endTime = '2023-03-06 00:00:00';
    protected $appkey = '63dc84c8d64e68613925c622';

    public function handle()
    {
        $this->info("开始".date("Y-m-d H:i:s"));
        $beginTime = strtotime($this->beginTime);
        for(; $beginTime<=strtotime($this->endTime);) {
            $this->info('doing:' . date("Y-m-d H:i:s", $beginTime));
            $params = [
                'appkey' => $this->appkey,
                'startDate' => date("Y-m-d", $beginTime),
                'endDate' => date("Y-m-d", $beginTime),
                'perPage' => 99999,
                'page' => 1
            ];
            $res = HttpClient::connect('Umeng', 'Uapp')->eventList($params);
            sleep(2);
            $events = data_get($res, 'eventInfo', []);
            foreach ($events as $event) {
                $user = HttpClient::connect('Umeng', 'Uapp')->eventGetUniqueUser([
                    'appkey' => $this->appkey,
                    'startDate' => date("Y-m-d", $beginTime),
                    'endDate' => date("Y-m-d", $beginTime),
                    'eventName' => $event['name'],
                ]);
                sleep(2);
                $paramList = HttpClient::connect('Umeng', 'Uapp')->eventParamList([
                    'appkey' => $this->appkey,
                    'startDate' => date("Y-m-d", $beginTime),
                    'endDate' => date("Y-m-d", $beginTime),
                    'eventId' => $event['id'],
                ]);
                sleep(2);
                if ($paramList['paramInfos']){
                    $paramValue = HttpClient::connect('Umeng', 'Uapp')->eventParamGetValueList([
                        'appkey' => $this->appkey,
                        'startDate' => date("Y-m-d", $beginTime),
                        'endDate' => date("Y-m-d", $beginTime),
                        'eventName' => $event['name'],
                        'eventParamName' => $paramList['paramInfos'][0]['name']
                    ]);
                    sleep(2);
                    foreach ($paramValue['paramInfos'] as $value){
                        $data = [
                            'date' => date("Y-m-d", $beginTime),
                            'event_name' => $event['name'],
                            'event_display' => $event['displayName'],
                            'params' => urldecode($value['name']),
                            'count' => $value['count']
                        ];
                        Db::table('temp_20230307_p')->insert($data);
                    }
                }else{
                    $data = [
                        'date' => date("Y-m-d", $beginTime),
                        'event_name' => $event['name'],
                        'event_display' => $event['displayName'],
                        'params' => '',
                        'count' => $event['count']
                    ];
                    Db::table('temp_20230307_p')->insert($data);
                }


            }
            $beginTime += 60 * 60 * 24;
        }

    }

}