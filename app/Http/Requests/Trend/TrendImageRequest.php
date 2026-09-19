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
    }

    public function rules(): array
    {
        return [
            'payload' => ['required', 'string'],
            'conversation_uuid' => ['required', 'uuid'],
            'user_message' => ['required', 'string', 'max:1000'],
            'selected_model_id' => ['sometimes', 'integer'],
            'state' => ['sometimes', 'array'],
            'state.parameters' => ['sometimes', 'array'],
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

