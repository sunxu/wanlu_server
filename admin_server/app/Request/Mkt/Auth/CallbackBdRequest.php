<?php

declare(strict_types=1);

namespace App\Request\Mkt\Auth;

use App\Request\FormRequest;

class CallbackBdRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'appId' => 'string|required',
            'authCode' => 'string|required',
            'userId' => 'string|required',
        ];
    }
}