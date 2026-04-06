<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemifyRequest extends FormRequest
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
            'extraction_tasks' => ['nullable', 'array'],
            'extraction_tasks.*' => ['string', 'max:255'],
            'enrichment_tasks' => ['nullable', 'array'],
            'enrichment_tasks.*' => ['string', 'max:255'],
            'data' => ['nullable', 'string'],
            'node_name' => ['nullable', 'array'],
            'node_name.*' => ['string', 'max:255'],
            'run_in_background' => ['nullable', 'boolean'],
        ];
    }
}