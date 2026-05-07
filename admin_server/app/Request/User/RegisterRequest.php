<?php

declare(strict_types=1);

namespace App\Request\User;

use App\Request\FormRequest;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_name' => 'string|required',
        ];
    }
}