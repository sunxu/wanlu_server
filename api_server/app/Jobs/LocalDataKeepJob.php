<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/4/7
 */

namespace App\Jobs;

use App\Services\Common\ActiveKeep;
use App\Services\Helper\Tools\Dding;

class LocalDataKeepJob extends BaseJob
{

    public function handle()
    {
        $day = $this->data['day'];
        $appcode = $this->data['appcode'];
        $type = $this->data['type'];
        ActiveKeep::getInstance()->setAppcode($appcode)->keepData($day, $type);
    }

    public function failed(\Exception $exception)
    {
        $error_msg = '【任务队列：留存统计】尝试' . $this->tries . '次依然失败，请检查' . PHP_EOL;
        $error_msg .= '任务参数：' . json_encode($this->data);
        Dding::push($error_msg);
    }
}