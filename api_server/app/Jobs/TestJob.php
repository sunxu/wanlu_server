<?php

namespace App\Jobs;

class TestJob extends BaseJob
{
    public $tries = 1;

    public function handle()
    {

    }
}