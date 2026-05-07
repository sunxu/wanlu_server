<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest as BaseRequest;

abstract class FormRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    abstract public function rules(): array;
}
