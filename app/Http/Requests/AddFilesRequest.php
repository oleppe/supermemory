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
