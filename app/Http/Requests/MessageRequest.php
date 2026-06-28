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
    private const CHAT3_SEO_SUB_TOOL_IDS = [13, 14, 15, 16, 17, 18, 20];

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
            'idempotency_key' => ['nullable', 'string', 'max:150'],
            'debug' => ['nullable', 'boolean'],
            'tool' => ['nullable', 'string', 'max:100'],
            'tool_key' => ['nullable', 'string', 'max:100'],
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

        if (in_array($subToolId, self::CHAT3_SEO_SUB_TOOL_IDS, true)) {
            return array_merge($rules, $this->chat3SeoStateRules());
        }

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
        $this->normalizeMessageTextFields();

        if (! $this->filled('sub_tool_id')) {
            $conversationId = $this->input('conversation_id');
            $conversationUuid = $this->input('conversation_uuid');

            if (! $conversationId && ! $conversationUuid) {
                $this->normalizeChat3SeoStateForValidation();

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

        $this->normalizeChat3SeoStateForValidation();
    }

    private function normalizeMessageTextFields(): void
    {
        $messageText = $this->firstFilledScalar(['content', 'user_message', 'message']);

        if ($messageText === null) {
            return;
        }

        $merge = [];

        if (! $this->filled('content')) {
            $merge['content'] = $messageText;
        }

        if (! $this->filled('user_message')) {
            $merge['user_message'] = $messageText;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    private function normalizeChat3SeoStateForValidation(): void
    {
        $subToolId = (int) $this->input('sub_tool_id');
        $toolKey = strtolower(trim((string) ($this->input('tool_key') ?: $this->input('tool'))));
        $modelKey = strtolower(trim((string) $this->input('model_key')));
        $isChat3SeoTool = in_array($subToolId, self::CHAT3_SEO_SUB_TOOL_IDS, true)
            || in_array($toolKey, [
                'ai_keyword_generator',
                'ai_meta_description_generator',
                'ai_content_analyzer',
                'ai_content_optimizer',
                'ai_detector',
                'ai_humanizer',
                'business_name_generator',
            ], true)
            || in_array($modelKey, [
                'keyword_generator',
                'meta_description_generator',
                'content_analyzer',
                'content_optimizer',
                'ai_detector',
                'ai_humanizer',
                'business_name_generator',
            ], true);

        if (! $isChat3SeoTool) {
            return;
        }

        $state = $this->input('state');
        $state = is_array($state) ? $state : [];
        $messageText = $this->firstFilledScalar(['content', 'user_message', 'message']);

        if ($messageText !== null) {
            $state['content'] = $this->filledNestedString($state, 'content') ?? $messageText;
            $state['user_message'] = $this->filledNestedString($state, 'user_message') ?? $messageText;

            if ($subToolId === 13 || $toolKey === 'ai_keyword_generator' || $modelKey === 'keyword_generator') {
                $state['topic'] = $this->filledNestedString($state, 'topic') ?? $messageText;
            }
        }

        if (
            ! in_array($subToolId, [17, 18, 20], true)
            && ! in_array($toolKey, ['ai_detector', 'ai_humanizer', 'business_name_generator'], true)
            && ! in_array($modelKey, ['ai_detector', 'ai_humanizer', 'business_name_generator'], true)
        ) {
            $state['results_count'] = $this->positiveInteger($state['results_count'] ?? null, 3, 100);
        }

        if (! is_array($state['extra_options'] ?? null)) {
            $state['extra_options'] = [];
        }

        $this->merge(['state' => $state]);
    }

    private function firstFilledScalar(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->input($key);

            if (! is_scalar($value)) {
                continue;
            }

            $text = trim((string) $value);

            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function filledNestedString(array $state, string $key): ?string
    {
        $value = $state[$key] ?? null;

        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function positiveInteger(mixed $value, int $fallback, int $max): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max(1, min($max, (int) $value));
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

    private function chat3SeoStateRules(): array
    {
        return [
            'state.topic' => ['nullable', 'string', 'max:5000'],
            'state.user_message' => ['nullable', 'string', 'max:5000'],
            'state.industry' => ['nullable', 'string', 'max:150'],
            'state.target_audience' => ['nullable', 'string', 'max:250'],
            'state.language' => ['nullable', 'string', 'max:80'],
            'state.business_idea' => ['nullable', 'string', 'max:5000'],
            'state.keyword_type' => ['nullable', 'string', 'max:100'],
            'state.search_intent' => ['nullable', 'string', 'max:100'],
            'state.location' => ['nullable', 'string', 'max:150'],
            'state.results_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'state.include_long_tail' => ['nullable', 'boolean'],
            'state.include_clusters' => ['nullable', 'boolean'],
            'state.content' => ['nullable', 'string', 'max:100000'],
            'state.page_title' => ['nullable', 'string', 'max:500'],
            'state.primary_keyword' => ['nullable', 'string', 'max:250'],
            'state.tone' => ['nullable', 'string', 'max:100'],
            'state.name_style' => ['nullable', 'string', 'max:100'],
            'state.length' => ['nullable', 'string', 'max:100'],
            'state.max_characters' => ['nullable', 'integer', 'min:50', 'max:320'],
            'state.include_cta' => ['nullable', 'boolean'],
            'state.analysis_goal' => ['nullable', 'string', 'max:250'],
            'state.target_keyword' => ['nullable', 'string', 'max:250'],
            'state.content_type' => ['nullable', 'string', 'max:150'],
            'state.audience' => ['nullable', 'string', 'max:250'],
            'state.checks' => ['nullable', 'array'],
            'state.checks.*' => ['string', 'max:100'],
            'state.keywords' => ['nullable', 'array'],
            'state.keywords.*' => ['string', 'max:100'],
            'state.avoid_words' => ['nullable', 'array'],
            'state.avoid_words.*' => ['string', 'max:100'],
            'state.detail_level' => ['nullable', 'string', 'max:100'],
            'state.include_recommendations' => ['nullable', 'boolean'],
            'state.optimization_goal' => ['nullable', 'string', 'max:250'],
            'state.secondary_keywords' => ['nullable', 'array'],
            'state.secondary_keywords.*' => ['string', 'max:150'],
            'state.seo_level' => ['nullable', 'string', 'max:100'],
            'state.preserve_meaning' => ['nullable', 'boolean'],
            'state.preserve_keywords' => ['nullable', 'boolean'],
            'state.include_slogans' => ['nullable', 'boolean'],
            'state.include_domain_ideas' => ['nullable', 'boolean'],
            'state.include_explanation' => ['nullable', 'boolean'],
            'state.analysis_depth' => ['nullable', 'string', 'max:100'],
            'state.detection_focus' => ['nullable', 'string', 'max:150'],
            'state.include_score' => ['nullable', 'boolean'],
            'state.include_evidence' => ['nullable', 'boolean'],
            'state.include_rewrite_tips' => ['nullable', 'boolean'],
            'state.humanize_level' => ['nullable', 'string', 'max:100'],
            'state.last_output' => ['nullable', 'string', 'max:100000'],
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
