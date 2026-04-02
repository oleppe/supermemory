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
            'dataset_id' => ['nullable', 'uuid', 'required_without:dataset_name'],
            'dataset_name' => ['nullable', 'string', 'max:255', 'required_without:dataset_id'],
            'node_set' => ['nullable', 'array'],
            'node_set.*' => ['string', 'max:255'],
            'run_cognify' => ['nullable', 'boolean'],
            'run_in_background' => ['nullable', 'boolean'],
            'custom_prompt' => ['nullable', 'string'],
            'ontology_key' => ['nullable', 'array'],
            'ontology_key.*' => ['string', 'max:255'],
        ];
    }
}
