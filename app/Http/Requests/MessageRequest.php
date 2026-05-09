<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

class MessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $conversationId = $this->input('conversation_id');

        if (! $this->user() || ! $conversationId) {
            return false;
        }

        return Conversation::where('id', $conversationId)
            ->where('user_id', $this->user()->id)
            ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => 'required|string|max:10000',
            'conversation_id' => [
                'required',
                Rule::exists('conversations', 'id')->where('user_id', $this->user()->id),
            ],
            'role' => 'required|in:user',
            'idempotency_key' => 'required|uuid',
            'task_options' => ['nullable', 'array'],
            'task_options.search_mode' => ['required_with:task_options', 'string', 'in:on,off'],
            'task_options.web_search_max_results' => ['nullable', 'integer', 'min:1', 'max:10'],
            'task_options.web_search_total_results' => ['nullable', 'integer', 'min:1', 'max:20'],
            'task_options.max_tokens' => ['nullable', 'integer', 'min:100', 'max:8000'],
            'task_options.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('Message request validation failed.', [
            'user_id' => optional($this->user())->id,
            'route' => optional($this->route())->uri(),
            'method' => $this->method(),
            'input' => $this->all(),
            'errors' => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }
}
