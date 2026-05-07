<?php
/**
 * 任务基类
 */

namespace App\Jobs;

use App\Services\Helper\Tools\LogPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BaseJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    /**
     * 任务可以执行的秒数 (超时时间)。
     *
     * @var int
     */
    public $timeout = 60 * 9;

    /**
     * 失败执行次数
     * @var int
     */
    public $tries = 5;

    /**
     * Create a new job instance.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

    }

    /**
     * Execute the job failed.
     *
     * @return void
     */
    public function failed(\Exception $exception)
    {
        LogPush::error(class_basename(__CLASS__), [
            'data' => $this->data,
            'message' => $exception->getMessage()
        ]);
    }
}
