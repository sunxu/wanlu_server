<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\Role\IndexRequest;
use App\Request\Role\StoreRequest;
use App\Resource\Role\RoleInfo;
use App\Service\RoleService;
use Hyperf\Di\Annotation\Inject;

class RoleController extends AbstractController
{
    /**
     * @Inject
     * @var RoleService
     */
    protected $roleService;

    public function index(IndexRequest $request)
    {
        $list = $this->roleService->roleList($request->all());

        return $this->responsePaginator($list->items(), $list);
    }

    public function detail(int $roleId)
    {
        $roleInfo = $this->roleService->roleInfo($roleId);

        return $this->responseSuccess(new RoleInfo(object_to_arr($roleInfo)));
    }

    public function store(StoreRequest $request)
    {
        $this->roleService->addRole($request->all());

        return $this->responseSuccess();
    }

    public function edit(int $roleId, StoreRequest $request)
    {
        $this->roleService->editRole($roleId, $request->all());

        return $this->responseSuccess();
    }
}