<?php

declare(strict_types=1);

namespace App\Request\UnionMedia;

use App\Request\FormRequest;

class ExportCsjExcelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'excel' => 'file|required|mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet|mimes:xlsx',
            'company_id' => 'integer|required',
        ];
    }
}