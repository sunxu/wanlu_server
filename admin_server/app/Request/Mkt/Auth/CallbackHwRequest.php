<?php

declare(strict_types=1);

namespace App\Request\Mkt\Auth;

use App\Request\FormRequest;

class CallbackHwRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_id' => 'string|required',
            'authorization_code' => 'string|required',
            'user_id' => 'string|required',
        ];
    }
}