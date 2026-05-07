<?php

declare(strict_types=1);

namespace App\Request\Mkt\Auth;

use App\Request\FormRequest;

class CallbackViRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'clientId' => 'string|required',
            'code' => 'string|required'
        ];
    }
}