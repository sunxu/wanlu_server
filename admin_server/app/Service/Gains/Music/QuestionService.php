<?php

namespace App\Service\Gains\Music;

use App\Library\Aliyun\Oss;
use App\Library\Transverter\Transverter;
use App\Utils\Ding;
use Hyperf\DbConnection\Db;

class QuestionService
{
    public function dailyData()
    {
        $beginTime = strtotime("yesterday");
        $endTime = $beginTime + 24 * 60 * 60;
        $appCode = 'ammusic';
        $query = Db::connection('mis')->table("user_{$appCode}_account as a")
            ->leftJoin("ad_put_click_{$appCode} as b", "a.user_id", "b.user_id")
            ->whereBetween('a.created_time', [$beginTime, $endTime]);
        $questionQuery = Db::connection('mis')->table("music_award_record_{$appCode}")->whereBetween('award_time', [$beginTime, $endTime]);
        $data = [
            'date' => date("Y-m-d", $beginTime),
            'install' => $query->count('a.user_id'),
            'guiyin' => $query->whereNotNull('b.user_id')->count('b.user_id'),
            'active' => $query->whereNotNull('b.user_id')->where('b.activate_time', '>', 0)
                ->count('b.user_id'),
            'register' => $query->where('a.openid', '<>', '')->count('a.user_id'),
            'personTime' => $questionQuery->distinct()->count('user_id'),
            'questionTime' => $questionQuery->count(),
        ];
        $img_path = BASE_PATH . '/runtime/logs/' . uniqid() . '.jpg';
        $res = make(Transverter::class, [['records' => [$data]]])->toImage($img_path, 'transerter_layout/question');
        if(!$res){
            return;
        }
        $Oss = make(Oss::class);
        $ossUrl = $Oss->putPublicBucket($img_path);
        unlink($img_path);
        $message = '**日期：' . $data['date'] . '**';
        $message .= PHP_EOL;
        $message .= '**用户答题**';
        $message .= '![](' . $ossUrl['oss_request_url'] . ')';
        make(Ding::class)->setRobotCode('daily_report')->pushMarkdown('日报', $message);
    }
}