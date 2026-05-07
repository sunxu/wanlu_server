<?php
declare(strict_types=1);
/**
 * 废弃待删除
 * Author: jyk-wsy
 * Date: 2022/4/6
 */

namespace App\Jobs;

use App\Services\Common\ActiveKeep;
use App\Services\Helper\Tools\Dding;

class ActiveKeepJob extends BaseJob
{
    public function handle()
    {
        $day = (int)$this->data['day'];
        $page = (int)$this->data['page'];
        $appcode = $this->data['appcode'];
        $total_page = $this->data['total_page'];
        $ActiveKeep = ActiveKeep::getInstance()->setAppcode($appcode);
        $ActiveKeep->insertDevice($page, $day);

        if ($page < $total_page) {
            $job_params = [];
            $job_params['appcode'] = $appcode;
            $job_params['day'] = $day;
            $job_params['page'] = $page + 1;
            $job_params['total_page'] = $total_page;
            dispatch(new ActiveKeepJob($job_params));
        } else {
            $ActiveKeep->clearDevice($day);
        }

    }

    public function failed(\Exception $exception)
    {
        $error_msg = '【任务队列：活跃留存】尝试' . $this->tries . '次依然失败，请检查' . PHP_EOL;
        $error_msg .= '任务参数：' . json_encode($this->data);
        Dding::push($error_msg);
    }
}