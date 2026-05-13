<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubToolUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|min:5|string',
            'endpoint' => ['nullable', 'string', 'max:255'],
            'website' => 'nullable|url|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5024',
            'prompt_placeholder' => 'nullable|min:5|string',
        ];
    }
}
