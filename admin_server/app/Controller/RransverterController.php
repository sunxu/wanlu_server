<?php

declare(strict_types=1);

namespace App\Controller;

use App\Utils\RedisManager;
use Hyperf\View\RenderInterface;

class RransverterController extends AbstractController
{
    public function index(string $key)
    {
        $redis = RedisManager::get();
        $data = $redis->get("transverter:{$key}");
        $data = json_decode($data, true);
        $Render = di(RenderInterface::class);

        return $Render->render(data_get($data, 'layout'), ['data' => data_get($data, 'data')]);
    }
}