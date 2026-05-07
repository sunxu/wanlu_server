<?php
declare(strict_types=1);

namespace App\Process;

use App\Constants\MktConst;
use Hyperf\AsyncQueue\Process\ConsumerProcess;

class TaskProcess extends ConsumerProcess
{
    protected $queue = 'task';
}