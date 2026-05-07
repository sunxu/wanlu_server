<?php

declare(strict_types=1);

namespace App\Library\Transverter;

use App\Utils\Log;
use App\Utils\RedisManager;
use Psr\Log\LogLevel;

class Transverter
{
    protected $data = null;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function toImage(string $filePath, string $layout): bool
    {
        $uniqid = uniqid();
        $redis = RedisManager::get();
        $redis->set("transverter:{$uniqid}", json_encode(['data' => $this->data, 'layout' => $layout]));
        $redis->expire("transverter:{$uniqid}", 60 * 60 * 24);
        $port = config('server.servers.0.port');
        $url = "http://127.0.0.1:{$port}/uc/transverter/{$uniqid}";
        /*执行系统命令生成图片*/
        try {
            $cli_command = 'wkhtmltoimage --width 1400 --quality 100 "' . $url . '" ' . $filePath;
            exec($cli_command);//需去掉ini exec方法禁用设置
        } catch (\Exception $exception) {
            var_dump($exception->getMessage());
            Log::system(LogLevel::ERROR, '生成图片失败', [$exception->getMessage(), $exception->getCode()]);
            return false;
        }
        return true;
    }
}