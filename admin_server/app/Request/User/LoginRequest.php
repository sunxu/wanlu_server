<?php

declare(strict_types=1);

namespace App\Request\User;

use App\Request\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => 'string|required',
            'password' => 'string|required',
        ];
    }
}