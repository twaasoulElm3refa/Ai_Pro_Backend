<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminCostIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
            'conversation_id' => ['nullable', 'integer'],
            'conversation_uuid' => ['nullable', 'string', 'max:255'],
            'sub_tool_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'today' => ['nullable', 'boolean'],
            'limited' => ['nullable', 'boolean'],
            'min_total_tokens' => ['nullable', 'integer', 'min:0'],
            'max_total_tokens' => ['nullable', 'integer', 'min:0'],
            'min_total_cost' => ['nullable', 'numeric', 'min:0'],
            'max_total_cost' => ['nullable', 'numeric', 'min:0'],
            'sort_by' => ['nullable', 'string', 'max:64'],
            'sort_direction' => ['nullable', 'string', 'max:10'],
        ];
    }
}
