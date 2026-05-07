<?php
declare(strict_types=1);

namespace App\Job\Task;

use App\Model\ReportTask;
use App\Service\Task\TaskService;
use App\Utils\Ding;
use Exception;
use Hyperf\AsyncQueue\Job;

class TaskJob extends Job
{
    /**
     * 失败后的重试次数
     * @var int
     */
    protected $maxAttempts = 0;

    protected $taskId;

    public function __construct(int $taskId)
    {
        $this->taskId = $taskId;
    }

    /**
     * @throws Exception
     */
    public function handle()
    {
        $model = ReportTask::find($this->taskId);

        if ($model->status == 1) {
            return;
        }

        $func = $model->info['type'];
        $args = [$model->app_code, $model->info['time_start'], $model->info['time_end']];
        try {
            $result = call_user_func_array([make(TaskService::class), $func], $args);
        } catch (Exception $e) {
            // 执行失败
            $model->status = 2;
            $model->save();
            $this->dingPush($e->getMessage(), [
                'app_code' => $model->app_code,
                'info' => $model->info,
                'type' => $model->type,
            ]);
            throw new Exception($e->getMessage());
        }

        $model->status = 1;
        $model->result = $result;
        $model->save();
    }

    /**
     * @param string $message
     * @param array $info
     * @return bool
     */
    public function dingPush(string $message, array $info)
    {
        try {
            return make(Ding::class)->setRobotCode('warning')
                ->pushText(json_encode([
                    'task' => $info,
                    'class' => __CLASS__,
                    'exception' => $message
                ], JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
        }
    }
}
