<?php

declare(strict_types=1);

namespace App\Request\Role;

use App\Request\FormRequest;

class StoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role_name' => 'string|required',
            'pages' => 'array',
            'apis' => 'array',
        ];
    }
}