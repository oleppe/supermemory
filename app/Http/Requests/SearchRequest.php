<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'rerank' => ['nullable', 'boolean'],
            'conversationHistory' => ['nullable', 'array', 'max:12'],
            'conversationHistory.*.role' => ['required_with:conversationHistory', 'string', 'in:user,assistant'],
            'conversationHistory.*.content' => ['required_with:conversationHistory', 'string', 'max:4000'],
        ];
    }
}
