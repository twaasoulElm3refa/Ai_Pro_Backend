<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoyasarDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => is_string($this->description) ? trim($this->description) : $this->description,
            'idempotency_key' => is_string($this->idempotency_key)
                ? trim($this->idempotency_key)
                : $this->idempotency_key,
            'locale' => strtolower((string) ($this->locale ?: 'en')),
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['bail', 'required', 'numeric', 'decimal:0,2', 'min:1', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:127'],
            'idempotency_key' => ['required', 'uuid'],
            'locale' => ['required', Rule::in(['ar', 'en', 'ru', 'fr', 'zh'])],
        ];
    }
}
