<?php

declare(strict_types=1);

namespace App\Request\User;

use App\Request\FormRequest;

class UserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_name' => 'string|required',
            'role_ids' => 'array',
            'app_ids' => 'array',
            'password' => 'string',
        ];
    }
}