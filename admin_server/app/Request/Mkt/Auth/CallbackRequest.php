<?php

declare(strict_types=1);

namespace App\Request\Mkt\Auth;

use App\Request\FormRequest;

class CallbackRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'app_id' => 'string|required',
            'auth_code' => 'string|required',
        ];
    }
}