<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/3/29
 */

namespace App\Controller;


abstract class AbstractAction extends AbstractController
{

    /**
     * 接口入口方法
     *
     * @return mixed
     */
    abstract public function handle();

    /**
     * @return string[] 规则
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * 获取已定义验证规则的错误消息.
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * 获取默认参数设置.
     */
    public function default(): array
    {
        return [];
    }


    /**
     * 自定义属性
     *
     * @return array
     */
    public function attributes(): array
    {
        return [];
    }

}