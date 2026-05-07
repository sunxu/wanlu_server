<?php

declare(strict_types=1);

use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\AsyncQueue\JobInterface;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Redis\RedisFactory;
use Hyperf\Utils\ApplicationContext;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LogLevel;
use App\Utils\Log;

if (!function_exists('create_uuid')) {
    /**
     * 生成 uuid.
     */
    function create_uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF)
        );
    }
}

if (!function_exists('di')) {

    /**
     * @param null $id
     * @return mixed|ContainerInterface
     */
    function di($id = null)
    {
        $container = ApplicationContext::getContainer();
        if ($id) {
            return $container->get($id);
        }
        return $container;
    }
}

if (!function_exists('sequence_sender')) {
    /**
     * 发号器.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    function sequence_sender(): int
    {
        $container = \Hyperf\Utils\ApplicationContext::getContainer();
        $generator = $container->get(\Hyperf\Snowflake\IdGeneratorInterface::class);

        return $generator->generate();
    }
}

if (!function_exists('is_product')) {
    function is_product(): bool
    {
        return config('app_env') == 'product';
    }
}

if (!function_exists('is_local')) {
    function is_local(): bool
    {
        return config('app_env') == 'local';
    }
}

if (!function_exists('request')) {
    function request()
    {
        return di()->get(RequestInterface::class);
    }
}

if (!function_exists('response')) {
    function response()
    {
        return di()->get(ResponseInterface::class);
    }
}

if (!function_exists('judge')) {
    function judge($value1, $value2, $operator): bool
    {
        $bool = false;
        switch ($operator) {
            case 'lt':
                $bool = $value1 < $value2;
                break;
            case 'le':
                $bool = $value1 <= $value2;
                break;
            case 'gt':
                $bool = $value1 > $value2;
                break;
            case 'ge':
                $bool = $value1 >= $value2;
                break;
            case 'eq':
                $bool = $value1 == $value2;
                break;
            case 'ne':
                $bool = $value1 != $value2;
                break;
            case 'in':
                $bool = in_array($value1, $value2);
                break;
        }

        return $bool;
    }
}

if (!function_exists('get_client_ip')) {
    function get_client_ip()
    {
        $request = Context::get(ServerRequestInterface::class);
        $headers = $request->getHeaders();

        if (isset($headers['x-forwarded-for'][0]) && !empty($headers['x-forwarded-for'][0])) {
            return $headers['x-forwarded-for'][0];
        } elseif (isset($headers['x-real-ip'][0]) && !empty($headers['x-real-ip'][0])) {
            return $headers['x-real-ip'][0];
        }

        $serverParams = $request->getServerParams();
        return $serverParams['remote_addr'] ?? '';
    }
}

if (!function_exists('queue_push')) {
    /**
     * 投递消息
     * @param JobInterface $job
     * @param string $queue_key
     * @param int $delay
     * @return bool
     */
    function queue_push(JobInterface $job, string $queue_key = 'default', int $delay = 0): bool
    {
        $driver = di(DriverFactory::class)->get($queue_key);
        return $driver->push($job, $delay);
    }

}

if (!function_exists('queue_delete')) {
    /**
     * 删除消息
     * @param JobInterface $job
     * @param string $queue_key
     * @return bool
     */
    function queue_delete(JobInterface $job, string $queue_key = 'default'): bool
    {
        $driver = di(DriverFactory::class)->get($queue_key);
        return $driver->delete($job);
    }
}

if (!function_exists('object_to_arr')) {
    /**
     * 对象转数组
     * @param $object
     * @return array
     */
    function object_to_arr($object): array
    {
        if (!is_array($object) && !is_object($object)) {
            return $object;
        }
        return json_decode(json_encode($object), true);
    }
}

if (!function_exists('safe_div')) {
    /**
     * 除法
     * @param $num1
     * @param $num2
     * @param $round
     * @return float
     */
    function safe_div($num1, $num2, $round = 4)
    {
        $res = $num2 > 0 ? $num1 / $num2 : 0;

        return round($res, $round);
    }
}

if (!function_exists('msectime')) {
    /**
     * 毫秒时间戳
     * @return float
     */
    function msectime() {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }
}

if (!function_exists('delete_dir')) {
    /**
     * 删除当前目录及其目录下的所有目录和文件
     * @param string $path 待删除的目录
     * @param string $exception 特例目录 不删
     * @return void
     */
    function delete_dir(string $path, string $exception='')
    {
        if (!is_dir($path)) {
            return;
        }
        //扫描一个目录内的所有目录和文件并返回数组
        $dirs = scandir($path);
        foreach ($dirs as $dir) {
            //排除目录中的当前目录(.)和上一级目录(..)
            if ($dir == '.' || $dir == '..' || $dir == $exception){
                continue;
            }
            //如果是目录则递归子目录，继续操作
            $sonDir = $path.'/'.$dir;
            if (is_dir($sonDir)) {
                //递归删除
                delete_dir($sonDir);
                //目录内的子目录和文件删除后删除空目录
                @rmdir($sonDir);
            } else {

                //如果是文件直接删除
                @unlink($sonDir);
            }
        }
    }
}



if (!function_exists('redis_client')) {
    /**
     * 获取redis链接
     * @param string $poolName
     * @return \Hyperf\Redis\RedisProxy|mixed
     */
    function redis_client(string $poolName = 'default')
    {
        return di(RedisFactory::class)->get($poolName);
    }

}


if (!function_exists('array_map_safe')) {
    /**
     * @param callable|null $callback
     * @param array $array
     * @return void
     */
    function array_map_safe(?callable $callback, array $array)
    {
        array_map(function ($item) use ($callback){
            try {
                $callback($item);
            }catch (\Exception $e){
                var_dump(['message'=>$e->getMessage(), 'file'=>$e->getFile()]);
                Log::system(LogLevel::ERROR, "array_map_safe 任务执行失败", ['message'=>$e->getMessage(), 'file'=>$e->getFile()]);
            }
        }, $array);
    }
}

