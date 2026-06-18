<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Services\AI\DynamicToolConfigService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MessageRequest extends FormRequest
{
    private const LEGACY_SUB_TOOL_IDS = [3, 4, 5, 6, 7, 8];
    private const TEXT_SUMMARIZER_SUB_TOOL_ID = 2;

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
        $rules = [
            'user_id' => ['nullable', 'integer'],
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
            'content' => [
                'nullable',
                'string',
                'max:5000',
                Rule::requiredIf(fn (): bool =>
                    (int) $this->input('sub_tool_id') !== self::TEXT_SUMMARIZER_SUB_TOOL_ID
                    && (int) $this->input('sub_tool_id') !== 8
                    && ! $this->filled('message')
                    && ! $this->filled('user_message')
                ),
            ],
            'body' => [
                'nullable',
                'string',
                'max:100000',
            ],
            'title' => [
                'nullable',
                'string',
                'max:255',
            ],
            'task_key' => [
                'nullable',
                'string',
                'max:100',
            ],
            'regenerate' => [
                'nullable',
                'boolean',
            ],
            'previous_output' => [
                'nullable',
                'string',
                'max:100000',
            ],
            'message' => [
                'nullable',
                'string',
                'max:5000',
                Rule::requiredIf(fn (): bool =>
                    (int) $this->input('sub_tool_id') !== self::TEXT_SUMMARIZER_SUB_TOOL_ID
                    && (int) $this->input('sub_tool_id') !== 8
                    && ! $this->filled('content')
                    && ! $this->filled('user_message')
                ),
            ],
            'user_message' => [
                'nullable',
                'string',
                'max:5000',
                Rule::requiredIf(fn (): bool =>
                    (int) $this->input('sub_tool_id') !== self::TEXT_SUMMARIZER_SUB_TOOL_ID
                    && (int) $this->input('sub_tool_id') !== 8
                    && ! $this->filled('content')
                    && ! $this->filled('message')
                ),
            ],
            'role' => ['nullable', 'in:user'],
            'idempotency_key' => ['nullable', 'uuid'],
            'debug' => ['nullable', 'boolean'],
            'tool' => ['nullable', 'string', 'max:100'],
            'model_key' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'array'],
            'task_options' => ['nullable', 'array'],
            'task_options.search_mode' => ['required_with:task_options', 'string', 'in:on,off'],
            'task_options.web_search_max_results' => ['nullable', 'integer', 'min:1', 'max:10'],
            'task_options.web_search_total_results' => ['nullable', 'integer', 'min:1', 'max:20'],
            'task_options.max_tokens' => ['nullable', 'integer', 'min:100', 'max:8000'],
            'task_options.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
        ];

        $subToolId = (int) $this->input('sub_tool_id');
        $configService = app(DynamicToolConfigService::class);
        $config = $configService->configFor($subToolId);

        if ($config !== []) {
            return array_merge($rules, $configService->stateValidationRules($config));
        }

        if (in_array($subToolId, self::LEGACY_SUB_TOOL_IDS, true)) {
            return array_merge($rules, $this->legacyStateRules());
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('sub_tool_id')) {
            return;
        }

        $conversationId = $this->input('conversation_id');
        $conversationUuid = $this->input('conversation_uuid');

        if (! $conversationId && ! $conversationUuid) {
            return;
        }

        $query = Conversation::query();
        if ($this->user()) {
            $query->where('user_id', $this->user()->id);
        }

        $conversation = $conversationId
            ? (clone $query)->where('id', (int) $conversationId)->first()
            : (clone $query)->where('uuid', (string) $conversationUuid)->first();

        if ($conversation) {
            $this->merge(['sub_tool_id' => (int) $conversation->sub_tool_id]);
        }
    }

    private function legacyStateRules(): array
    {
        return [
            'state.content' => ['nullable', 'string', 'max:5000'],
            'state.content_type' => ['nullable', 'string', 'max:100'],
            'state.goal' => ['nullable', 'string', 'max:100'],
            'state.language' => ['nullable', 'string', 'max:50'],
            'state.tone' => ['nullable', 'string', 'max:50'],
            'state.platform' => ['nullable', 'string', 'max:50'],
            'state.audience' => ['nullable', 'string', 'max:150'],
            'state.purpose' => ['nullable', 'string', 'max:5000'],
            'state.email_type' => ['nullable', 'string', 'max:100'],
            'state.recipient' => ['nullable', 'string', 'max:250'],
            'state.sender_name' => ['nullable', 'string', 'max:150'],
            'state.subject_line' => ['nullable', 'string', 'max:500'],
            'state.call_to_action' => ['nullable', 'string', 'max:1000'],
            'state.include_subject' => ['nullable', 'boolean'],
            'state.topic' => ['nullable', 'string', 'max:5000'],
            'state.script_type' => ['nullable', 'string', 'max:100'],
            'state.duration' => ['nullable', 'string', 'max:100'],
            'state.format' => ['nullable', 'string', 'max:150'],
            'state.include_scene_notes' => ['nullable', 'boolean'],
            'state.product' => ['nullable', 'string', 'max:1000'],
            'state.brand_name' => ['nullable', 'string', 'max:150'],
            'state.product_features' => ['nullable', 'string', 'max:5000'],
            'state.target_audience' => ['nullable', 'string', 'max:250'],
            'state.include_bullets' => ['nullable', 'boolean'],
            'state.include_seo_keywords' => ['nullable', 'boolean'],
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
