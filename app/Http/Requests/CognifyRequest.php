<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CognifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dataset_id' => ['nullable', 'uuid', 'required_without:dataset_name'],
            'dataset_name' => ['nullable', 'string', 'max:255', 'required_without:dataset_id'],
            'run_in_background' => ['nullable', 'boolean'],
            'custom_prompt' => ['nullable', 'string'],
            'ontology_key' => ['nullable', 'array'],
            'ontology_key.*' => ['string', 'max:255'],
        ];
    }
}
