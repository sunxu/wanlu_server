<?php
declare(strict_types=1);
/**
 * 回本率统计
 * Author: jyk-wsy
 * Date: 2022/4/7
 */

namespace App\Jobs;

use App\Services\Common\Recovery;
use App\Services\Helper\Tools\Dding;

class LocalRecoveryJob extends BaseJob
{
    public function handle()
    {
        $day = $this->data['day'];
        $appcode = $this->data['appcode'];
        Recovery::getInstance()->setAppcode($appcode)->recoveryData($day);
    }

    public function failed(\Exception $exception)
    {
        $error_msg = '【任务队列：回本率计算】尝试' . $this->tries . '次依然失败，请检查' . PHP_EOL;
        $error_msg .= '任务参数：' . json_encode($this->data);
        Dding::push($error_msg);
    }

}