<?php
declare(strict_types=1);

namespace App\Job\Mkt;

use App\Service\Mkt\MktService;
use App\Utils\Ding;
use App\Utils\Log;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\AsyncQueue\Job;

class MktJob extends Job
{
    /**
     * 失败后的重试次数
     * @var int
     */
    protected $maxAttempts = 1;

    /**
     * 方法名
     * @var string
     */
    protected $func;

    /**
     * 方法参数
     * @var array
     */
    protected $args = [];

    public function __construct(string $func, array $args)
    {
        $this->func = $func;
        $this->args = $args;
    }

    /**
     * @throws Exception
     */
    public function handle()
    {
        $service = make(MktService::class);

        try {
            Log::http('info', '【MktJob】', [
                'service' => 'MktService',
                'func' =>  $this->func,
                'args' => $this->args,
            ]);
            call_user_func_array([$service, $this->func], $this->args);
        } catch (Exception $e) {
            $this->dingPush($e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param string $message
     * @return bool
     */
    public function dingPush(string $message)
    {
        try {
            return make(Ding::class)->setRobotCode('warning')
                ->pushText(json_encode([
                    'dir' => __DIR__,
                    'class' => __CLASS__,
                    'func' => $this->func,
                    'args' => $this->args,
                    'exception' => $message
                ], JSON_UNESCAPED_UNICODE));
        } catch (GuzzleException $e) {

        }
    }
}
