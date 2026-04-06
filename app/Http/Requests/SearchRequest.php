<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string'],
            'dataset_id' => ['nullable', 'uuid', 'required_without:dataset_name'],
            'dataset_name' => ['nullable', 'string', 'max:255', 'required_without:dataset_id'],
            'search_type' => ['nullable', Rule::in(['GRAPH_COMPLETION', 'CHUNKS', 'RAG_COMPLETION'])],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:50'],
            'only_context' => ['nullable', 'boolean'],
            'system_prompt' => ['nullable', 'string'],
            'node_name' => ['nullable', 'array'],
            'node_name.*' => ['string', 'max:255'],
        ];
    }
}
