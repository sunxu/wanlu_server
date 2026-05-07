<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Role;
use App\Model\UserRole;

class RoleService
{
    /**
     * 角色列表
     * @param array $params
     * @return \Hyperf\Contract\LengthAwarePaginatorInterface
     */
    public function roleList(array $params)
    {
        return Role::when($roleName = data_get($params, 'role_name'), function ($query) use ($roleName) {
                $query->where('role_name', 'like', "%{$roleName}%");
            })
            ->orderBy('updated_time', 'desc')
            ->orderBy('role_id', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)));
    }

    /**
     * 角色详情
     * @param int $roleId
     * @return Role|Role[]|\Hyperf\Database\Model\Collection|\Hyperf\Database\Model\Model
     */
    public function roleInfo(int $roleId)
    {
        return Role::findOrFail($roleId);
    }

    /**
     * 添加角色
     * @param array $params
     * @return void
     * @throws \Throwable
     */
    public function addRole(array $params): void
    {
        $role = new Role();
        $role->fill($params);
        $role->saveOrFail();
    }

    /**
     * 更新角色信息
     * @param int $roleId
     * @param array $params
     * @return void
     * @throws \Throwable
     */
    public function editRole(int $roleId, array $params): void
    {
        $role = Role::findOrFail($roleId);
        $role->fill($params);
        $role->saveOrFail();
    }

    public function getRelationUserId(int $roleId): array
    {
        return UserRole::where('role_id', $roleId)->get('user_id')->pluck('user_id')->toArray();
    }
}