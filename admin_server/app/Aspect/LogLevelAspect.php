<?php

namespace App\Aspect;

use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Psr\Log\LogLevel;

/**
 * @Aspect
 */
class LogLevelAspect extends AbstractAspect
{
    // 要切入的类或 Trait，可以多个，亦可通过 :: 标识到具体的某个方法，通过 * 可以模糊匹配
    public $classes = [
        'App\Utils\Log::access',
        'App\Utils\Log::system',
        'App\Utils\Log::sql',
        'App\Utils\Log::http',
    ];

    /**
     * 校验日志等级
     * @param ProceedingJoinPoint $proceedingJoinPoint
     * @return mixed
     * @throws \Hyperf\Di\Exception\Exception
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $objClass = new \ReflectionClass(LogLevel::class);
        $constants = $objClass->getConstants();
        $constants = array_flip($constants);
        $arguments = $proceedingJoinPoint->getArguments();
        if(!isset($constants[$arguments[0]])){
            throw new \Exception('日志等级错误');
        }
        $result = $proceedingJoinPoint->process();
        // 在调用后进行某些处理
        return $result;
    }
}