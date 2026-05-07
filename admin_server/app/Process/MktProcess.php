<?php
declare(strict_types=1);

namespace App\Process;

use App\Constants\MktConst;
use Hyperf\AsyncQueue\Process\ConsumerProcess;

class MktProcess extends ConsumerProcess
{
    protected $queue = MktConst::QUEUE_KEY;
}