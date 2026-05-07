<?php

declare(strict_types=1);
/**
 * 中间表数据计算
 */

namespace App\Jobs;

use App\Services\Common\MiddleEvent;
use App\Services\Helper\Tools\Dding;

class MiddleEventJob extends BaseJob
{

    /**
     * 任务可以执行的秒数 (超时时间)。
     *
     * @var int
     */
    public $timeout = 3600;

    /**
     * 失败执行次数
     * @var int
     */
    public $tries = 2;

    public function handle()
    {
        try {
            $day_time = $this->data['date'];
            $appcode = $this->data['appcode'];
            MiddleEvent::getInstance()->setAppCode($appcode)->setDate($day_time)->findAll();
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
