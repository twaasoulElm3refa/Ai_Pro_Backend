<?php

namespace App\Http\Requests\Trend;

use Illuminate\Foundation\Http\FormRequest;

class TrendImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->input('payload');

        if (! is_string($payload)) {
            return;
        }

        $decoded = json_decode($payload, true);

        if (is_array($decoded)) {
            $this->merge($decoded);
        }

        if (! $this->exists('user_message')) {
            $this->merge(['user_message' => '']);
        }

        if (! is_array($this->input('state'))) {
            $this->merge(['state' => ['parameters' => []]]);
        }
    }

    public function rules(): array
    {
        return [
            'payload' => ['required', 'string', 'json'],
            'conversation_uuid' => ['required', 'uuid'],
            'sub_tool_id' => ['required', 'integer'],
            'user_message' => ['nullable', 'string', 'max:1000'],
            'selected_model_id' => ['required', 'integer'],
            'state' => ['sometimes', 'array'],
            'state.parameters' => ['sometimes', 'array'],
            'debug' => ['sometimes', 'boolean'],
            'idempotency_key' => ['required', 'uuid'],
            'file' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:10240',
            ],
        ];
    }
}
