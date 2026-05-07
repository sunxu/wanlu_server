<?php

declare(strict_types=1);

namespace App\Resource\Role;

use Hyperf\Resource\Json\JsonResource;

class RoleInfo extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(): array
    {
        return [
            'role_id' => data_get($this, 'role_id'),
            'role_name' => data_get($this, 'role_name'),
            'pages' => data_get($this, 'pages'),
        ];
    }
}