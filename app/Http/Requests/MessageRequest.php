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
        if (! $this->user()) {
            return false;
        }

        $conversationId = $this->input('conversation_id');
        $conversationUuid = $this->input('conversation_uuid');

        if (! $conversationId && ! $conversationUuid) {
            return false;
        }

        $query = Conversation::query()->where('user_id', $this->user()->id);

        if ($conversationId) {
            return (clone $query)->where('id', (int) $conversationId)->exists();
        }

        return (clone $query)->where('uuid', (string) $conversationUuid)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sub_tool_id' => ['required', 'integer'],
            'conversation_uuid' => [
                'nullable',
                'uuid',
                'required_without:conversation_id',
                Rule::exists('conversations', 'uuid')->where('user_id', $this->user()->id),
            ],
            'conversation_id' => [
                'nullable',
                'integer',
                'required_without:conversation_uuid',
                Rule::exists('conversations', 'id')->where('user_id', $this->user()->id),
            ],
            'content' => ['nullable', 'string', 'max:5000', 'required_without_all:message,user_message'],
            'message' => ['nullable', 'string', 'max:5000', 'required_without_all:content,user_message'],
            'user_message' => ['nullable', 'string', 'max:5000', 'required_without_all:content,message'],
            'role' => ['nullable', 'in:user'],
            'idempotency_key' => ['nullable', 'uuid'],
            'debug' => ['nullable', 'boolean'],
            'state' => ['nullable', 'array'],
            'state.content' => ['nullable', 'string', 'max:5000'],
            'state.content_type' => ['nullable', 'string', 'max:100'],
            'state.goal' => ['nullable', 'string', 'max:100'],
            'state.language' => ['nullable', 'string', 'max:50'],
            'state.tone' => ['nullable', 'string', 'max:50'],
            'state.platform' => ['nullable', 'string', 'max:50'],
            'state.audience' => ['nullable', 'string', 'max:150'],
            'state.rewrite_mode' => ['nullable', 'string', 'max:50'],
            'state.change_level' => ['nullable', 'string', 'max:50'],
            'state.length' => ['nullable', 'string', 'max:50'],
            'state.hashtag_count' => ['nullable', 'integer', 'min:0', 'max:30'],
            'state.include_emojis' => ['nullable', 'boolean'],
            'state.results_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'state.number_of_headlines' => ['nullable', 'integer', 'min:1', 'max:20'],
            'state.headline_length' => ['nullable', 'string', 'max:50'],
            'state.last_output' => ['nullable', 'string', 'max:10000'],
            'state.extra_options' => ['nullable', 'array'],
            'state.extra_options.*' => ['string', 'max:150'],
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
