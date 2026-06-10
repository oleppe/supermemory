<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeDocumentOcrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pages' => ['required', 'array', 'min:1', 'max:12'],
            'pages.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'allowed_categories' => ['required', 'array', 'min:1', 'max:50'],
            'allowed_categories.*' => ['required', 'string', 'min:1', 'max:100', 'distinct'],
        ];
    }
}
