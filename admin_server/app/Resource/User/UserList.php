<?php

declare(strict_types=1);

namespace App\Resource\User;

use Hyperf\Resource\Json\JsonResource;

class UserList extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(): array
    {
        $filter = function (array $data, array $fields) {
            foreach ($data as $key => $item) {
                foreach ($item as $field => $value) {
                    if (!in_array($field, $fields)) {
                        unset($data[$key][$field]);
                    }
                }
            }
            return $data;
        };

        return [
            'user_id' => data_get($this, 'user_id'),
            'user_name' => data_get($this, 'user_name'),
            'status' => data_get($this, 'status'),
            'login_time' => data_get($this, 'login_time'),
            'created_time' => data_get($this, 'created_time'),
            'updated_time' => data_get($this, 'updated_time'),
            'roles' => $filter(data_get($this, 'roles') ?? [], ['role_id', 'role_name']),
            'apps' => $filter(data_get($this, 'apps') ?? [], ['app_id', 'app_name']),
        ];
    }
}