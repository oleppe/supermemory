<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLandingDemoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:120'],
            'use_case' => ['required', 'string', 'max:3000'],
            'monthly_volume' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'email' => trim((string) $this->input('email', '')),
            'company' => trim((string) $this->input('company', '')),
            'use_case' => trim((string) $this->input('use_case', '')),
            'monthly_volume' => trim((string) $this->input('monthly_volume', '')),
        ]);
    }
}
