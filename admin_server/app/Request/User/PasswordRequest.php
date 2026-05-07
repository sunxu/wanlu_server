<?php

declare(strict_types=1);

namespace App\Request\User;

use App\Request\FormRequest;

class PasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'old_password' => 'string|required',
            'password' => 'string|required',
            'retry_password' => 'string|required',
        ];
    }
}