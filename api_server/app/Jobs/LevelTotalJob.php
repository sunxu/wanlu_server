<?php

declare(strict_types=1);
/**
 * 24小时ROI计算
 * Author: wangshenyuan
 * Date: 2022/3/15
 */

namespace App\Jobs;

use App\Services\Common\LevelTotal;
use App\Services\Helper\Tools\Dding;

class LevelTotalJob extends BaseJob
{
    public function handle()
    {

        try {
            $day_time = $this->data['day_time'];
            $appcode = $this->data['appcode'];

            LevelTotal::getInstance()->setAppCode($appcode)->setDate((int)$day_time)->total();

        } catch (\Exception $e) {
            $this->release(5 * $this->attempts());
            echo $e->getMessage();
        }

    }

    public function failed(\Exception $exception)
    {
        $error_msg = '【任务队列：' . $this->queue . '】尝试' . $this->tries . '次依然失败，请检查' . PHP_EOL;
        $error_msg .= '任务参数：' . json_encode($this->data);
        Dding::push($error_msg);
    }
}
