<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FreeAiModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:main_free_ai_models,name',
            'description' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer',
        ];
    }
}
