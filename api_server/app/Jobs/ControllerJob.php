<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ControllerJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $controller;

    /**
     * 失败执行次数
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param $controller
     */
    public function __construct($controller)
    {
        $this->$controller = $controller;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (method_exists($this->controller, 'handle')) {
            $this->controller->handle();
        }
    }
}
