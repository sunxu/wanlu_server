<?php

declare(strict_types=1);

namespace App\Request\Launch\LaunchAd;

use App\Request\FormRequest;

class AdRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date_begin' => 'required|date',
            'date_end' => 'required|date',
            'page' => 'integer',
            'per_page' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }
}