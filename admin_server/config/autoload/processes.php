<?php

declare(strict_types=1);

return [
    Hyperf\Crontab\Process\CrontabDispatcherProcess::class,//定时任务 Crontab
    \App\Process\MktProcess::class,
    \App\Process\TaskProcess::class,
];
