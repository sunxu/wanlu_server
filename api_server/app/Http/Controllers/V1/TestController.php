<?php
/**
 * 测试控制器
 */

namespace App\Http\Controllers\V1;

use Laravel\Lumen\Routing\Controller;


class TestController extends Controller
{
    /**
     * 测试方法
     * @return void
     */
    public function handle()
    {
        echo 'test';
    }
}
