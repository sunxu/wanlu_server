<?php

namespace App\Jobs;

use Exception;

class ComJob extends BaseJob
{
    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        call_user_func_array([$this->data['class'], $this->data['func']], $this->data['args']);
    }
}
