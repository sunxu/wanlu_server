<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\ErrorCode;
use App\Request\User\IndexRequest;
use App\Request\User\LoginRequest;
use App\Request\User\PasswordRequest;
use App\Request\User\RegisterRequest;
use App\Request\User\UserRequest;
use App\Resource\User\UserList;
use App\Service\UserService;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;

class UserController extends AbstractController
{
    /**
     * @Inject
     * @var UserService
     */
    protected $userService;

    /**
     * 注册
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Throwable
     */
    public function register(RegisterRequest $request)
    {
        $this->userService->register($request->all());

        return $this->responseSuccess();
    }

    /**
     * 普通登录
     * @param LoginRequest $request
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function login(LoginRequest $request)
    {
        $token = $this->userService->login($request->all());

        return $this->responseSuccess(['token' => $token]);
    }

    /**
     * 获取用户信息
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function selfInfo()
    {
        $userInfo = Context::get('_userInfo');

        return $this->responseSuccess([
            'apis' => $userInfo['apis'] ?? [],
            'apps' => $userInfo['apps'] ?? [],
            'pages' => $userInfo['pages'] ?? [],
            'roles' => $userInfo['roles'] ?? [],
            'user_id' => $userInfo['user_id'] ?? '',
            'user_name' => $userInfo['user_name'] ?? '',
            'avatar' => $userInfo['avatar'] ?? '',
            'is_super' => $userInfo['is_super'] ?? 0,
        ]);
    }

    /**
     * 退出登录
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function logout()
    {
        $token = $this->request->getHeaderLine('X-TOKEN');
        $this->userService->moveToken($token);

        return $this->responseSuccess();
    }

    public function index(IndexRequest $request)
    {
        $list = $this->userService->userList($request->all());

        return $this->responsePaginator(UserList::collection(object_to_arr($list->items())), $list);
    }

    public function store(UserRequest $request)
    {
        $this->userService->addUser($request->all());

        return $this->responseSuccess();
    }

    public function edit(int $userId, UserRequest $request)
    {
        $this->userService->updateUser($userId, $request->all());

        return $this->responseSuccess();
    }

    public function editSelfPassword(PasswordRequest $request)
    {
        $params = $request->all();
        if (data_get($params, 'retry_password') !== data_get($params, 'password')) {
            return $this->responseError(ErrorCode::SERVER_ERROR, '密码不一致');
        }
        $userInfo = Context::get('_userInfo');
        if (md5(data_get($params, 'old_password') . data_get($userInfo, 'salt')) !== data_get($userInfo, 'password')) {
            return $this->responseError(ErrorCode::SERVER_ERROR, '密码错误');
        }
        $this->userService->updatePassword(data_get($userInfo, 'user_id'), data_get($params, 'password'));

        return $this->responseSuccess();
    }

    public function detail(int $userId)
    {
        $userInfo = $this->userService->getUserInfo($userId);

        return $this->responseSuccess($userInfo);
    }

    public function editPassword(int $userId)
    {
        $params = $this->request->all();
        if (data_get($params, 'retry_password') !== data_get($params, 'password')) {
            return $this->responseError(ErrorCode::SERVER_ERROR, '密码不一致');
        }
        $this->userService->updatePassword($userId, data_get($params, 'password'));

        return $this->responseSuccess();
    }

    public function editStatus(int $userId)
    {
        $this->userService->editStatus($userId);

        return $this->responseSuccess();
    }
}