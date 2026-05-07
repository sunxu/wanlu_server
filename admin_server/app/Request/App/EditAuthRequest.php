<?php

declare(strict_types=1);

namespace App\Request\App;

use App\Request\FormRequest;

class EditAuthRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'pages' => 'array',
            'apis' => 'array',
        ];
    }
}