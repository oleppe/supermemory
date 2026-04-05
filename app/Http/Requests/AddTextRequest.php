<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string'],
            'custom_id' => ['nullable', 'string', 'max:100'],
            'entity_context' => ['nullable', 'string', 'max:1500'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
