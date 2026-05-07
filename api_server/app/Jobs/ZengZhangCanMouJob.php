<?php

declare(strict_types=1);
/**
 * 增长参谋数据上报计划任务
 * Author: wangshenyuan
 * Date: 2022/2/28
 */

namespace App\Jobs;

use App\Services\Common\ZengZhangCanMou;
use App\Services\Helper\Tools\Dding;

class ZengZhangCanMouJob extends BaseJob
{
    public function handle()
    {

        switch ($this->data['action']) {
            case 'sendAttributionData'://归因上报
                try {
                    ZengZhangCanMou::getInstance()->setAppCode($this->data['app_code'])->sendAttributionData($this->data);
                } catch (\Exception $exception) {
//                    echo '处理次数：' . $this->attempts() . PHP_EOL;
//                    echo $exception->getMessage() . PHP_EOL;
                    $this->release(10 * $this->attempts());
                }
                break;
        }


    }

    public function failed(\Exception $exception)
    {
        $error_msg = '【任务队列：' . $this->queue . '】尝试' . $this->tries . '次依然失败，请检查' . PHP_EOL;
        $error_msg .= '任务参数：' . json_encode($this->data);
//        echo $error_msg;
        Dding::push($error_msg);
    }
}
