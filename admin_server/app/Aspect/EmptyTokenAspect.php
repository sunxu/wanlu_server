<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Service\AppService;
use App\Service\RoleService;
use App\Service\UserService;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;

/**
 * @Aspect
 */
class EmptyTokenAspect extends AbstractAspect
{
    const USER_SERVICE_UPDATE_USER = 'App\Service\UserService::updateUser';
    const USER_SERVICE_UPDATE_PASSWORD = 'App\Service\UserService::updatePassword';
    const USER_SERVICE_EDIT_STATUS = 'App\Service\UserService::editStatus';

    const APP_SERVICE_EDIT_AUTH = 'App\Service\AppService::editAppAuth';

    const ROLE_SERVICE_EDIT_ROLE = 'App\Service\RoleService::editRole';

    const TICK_FUNC = [
        self::USER_SERVICE_UPDATE_USER => 'emptyTokenByUserId',
        self::USER_SERVICE_UPDATE_PASSWORD => 'emptyTokenByUserId',
        self::USER_SERVICE_EDIT_STATUS => 'emptyTokenByUserId',

        self::APP_SERVICE_EDIT_AUTH => 'emptyTokenByAppId',
        self::ROLE_SERVICE_EDIT_ROLE => 'emptyTokenByRoleId',
    ];

    public $classes = [
        self::USER_SERVICE_UPDATE_USER,
        self::USER_SERVICE_UPDATE_PASSWORD,
        self::USER_SERVICE_EDIT_STATUS,
        self::APP_SERVICE_EDIT_AUTH,
        self::ROLE_SERVICE_EDIT_ROLE,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $result = $proceedingJoinPoint->process();
        $func = "{$proceedingJoinPoint->className}::{$proceedingJoinPoint->methodName}";
        if(isset(self::TICK_FUNC[$func])){
            $tickFunc = self::TICK_FUNC[$func];
            $this->$tickFunc($proceedingJoinPoint);
        }
        // 在调用后进行某些处理
        return $result;
    }

    protected function emptyTokenByUserId(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $arguments = $proceedingJoinPoint->getArguments();
        $userId = $arguments[0];
        make(UserService::class)->emptyToken($userId);
    }

    protected function emptyTokenByRoleId(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $arguments = $proceedingJoinPoint->getArguments();
        $roleId = $arguments[0];
        $userIds = make(RoleService::class)->getRelationUserId($roleId);
        $userIds = make(UserService::class)->getUserIdsNotSuper($userIds);
        make(UserService::class)->emptyToken($userIds);
    }

    protected function emptyTokenByAppId(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $arguments = $proceedingJoinPoint->getArguments();
        $appId = $arguments[0];
        $userIds = make(AppService::class)->getRelationUserId($appId);
        $userIds = make(UserService::class)->getUserIdsNotSuper($userIds);
        make(UserService::class)->emptyToken($userIds);
    }
}