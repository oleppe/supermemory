<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200'],
            'custom_id' => ['nullable', 'string', 'max:100'],
            'entity_context' => ['nullable', 'string', 'max:1500'],
            'metadata' => ['nullable', 'array'],
            'summary' => ['nullable', 'string'],
            'summary_custom_id' => ['nullable', 'string', 'max:100'],
            'summary_entity_context' => ['nullable', 'string', 'max:1500'],
            'summary_metadata' => ['nullable', 'array'],
        ];
    }
}
