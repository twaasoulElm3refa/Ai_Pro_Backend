<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Services\AI\DynamicToolConfigService;
use App\Services\BackgroundRemoverService;
use App\Services\ImagePromptGeneratorService;
use App\Services\ImageUpscalerService;
use App\Services\SpeechToTextService;
use App\Services\YouTubeSummarizerService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MessageRequest extends FormRequest
{
    private const LEGACY_SUB_TOOL_IDS = [3, 4, 5, 6, 7, 8];
    private const TEXT_SUMMARIZER_SUB_TOOL_ID = 2;
    private const RESUME_BUILDER_SUB_TOOL_ID = 19;
    private const IMAGE_GENERATOR_SUB_TOOL_ID = 21;
    private const BACKGROUND_REMOVER_SUB_TOOL_ID = 22;
    private const IMAGE_UPSCALER_SUB_TOOL_ID = 23;
    private const IMAGE_PROMPT_GENERATOR_SUB_TOOL_ID = 24;
    private const YOUTUBE_SUMMARIZER_SUB_TOOL_ID = 25;
    private const SPEECH_TO_TEXT_SUB_TOOL_ID = 26;
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
            'payload' => ['nullable', 'json', 'max:100000'],
        ];

        $subToolId = (int) $this->input('sub_tool_id');

        if ($this->isResumeBuilderRequest()) {
            return array_merge($rules, $this->resumeBuilderRules());
        }

        if ($this->isBackgroundRemoverRequest()) {
            return array_merge($rules, $this->backgroundRemoverRules());
        }

        if ($this->isImageUpscalerRequest()) {
            return array_merge($rules, $this->imageUpscalerRules());
        }

        if ($subToolId === self::IMAGE_GENERATOR_SUB_TOOL_ID) {
            return array_merge($rules, $this->imageGeneratorRules());
        }

        if ($this->isImagePromptGeneratorRequest()) {
            return array_merge($rules, $this->imagePromptGeneratorRules());
        }

        if ($this->isYouTubeSummarizerRequest()) {
            return array_merge($rules, $this->youTubeSummarizerRules());
        }

        if ($this->isSpeechToTextRequest()) {
            return array_merge($rules, $this->speechToTextRules());
        }

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

    private function imageGeneratorRules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:10000'],
            'message' => ['nullable', 'string', 'max:10000'],
            'user_message' => ['required', 'string', 'max:10000'],
            'state' => ['required', 'array'],
            'state.provider' => ['nullable', 'string', 'max:100'],
            'state.negative_prompt' => ['nullable', 'string', 'max:5000'],
            'state.size' => [
                'required',
                Rule::in(['512x512', '768x768', '1024x1024', '1024x1536', '1536x1024']),
            ],
            'state.quality' => ['required', Rule::in(['low', 'medium', 'high'])],
            'state.results_count' => ['required', 'integer', 'min:1', 'max:4'],
            'state.output_format' => ['required', Rule::in(['png', 'jpg', 'jpeg', 'webp'])],
            'state.seed' => ['nullable', 'integer'],
            'state.extra_options' => ['nullable', 'array', 'max:20'],
            'state.extra_options.*' => ['string', 'max:150'],
            'state.last_output' => ['nullable'],
        ];
    }

    private function imagePromptGeneratorRules(): array
    {
        return [
            'sub_tool_id' => ['required', 'integer', Rule::in([self::IMAGE_PROMPT_GENERATOR_SUB_TOOL_ID])],
            'content' => ['nullable', 'string', 'max:10000'],
            'message' => ['nullable', 'string', 'max:10000'],
            'user_message' => ['required', 'string', 'max:10000'],
            'state' => ['required', 'array'],
            'state.content' => ['nullable', 'string', 'max:10000'],
            'state.language' => ['required', 'string', 'max:80'],
            'state.style' => ['required', 'string', 'max:150'],
            'state.aspect_ratio' => ['required', 'string', 'max:30'],
            'state.camera' => ['required', 'string', 'max:150'],
            'state.lighting' => ['required', 'string', 'max:150'],
            'state.negative_prompt' => ['nullable', 'string', 'max:5000'],
            'state.text_policy' => ['required', 'string', 'max:150'],
            'state.face_policy' => ['required', 'string', 'max:150'],
            'state.results_count' => ['required', 'integer', 'min:1', 'max:5'],
            'state.extra_options' => ['nullable', 'array', 'max:20'],
            'state.extra_options.*' => ['string', 'max:150'],
            'state.last_output' => ['nullable', 'string', 'max:100000'],
        ];
    }

    private function youTubeSummarizerRules(): array
    {
        return [
            'sub_tool_id' => ['required', 'integer', Rule::in([self::YOUTUBE_SUMMARIZER_SUB_TOOL_ID])],
            'content' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:2000'],
            'user_message' => [
                'required',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (YouTubeSummarizerService::normalizeYouTubeUrl($value) === null) {
                        $fail('The '.$attribute.' must be a valid YouTube video URL.');
                    }
                },
            ],
            'tool' => ['required', Rule::in([YouTubeSummarizerService::TOOL_KEY])],
            'tool_key' => ['required', Rule::in([YouTubeSummarizerService::TOOL_KEY])],
            'model_key' => ['required', Rule::in([YouTubeSummarizerService::MODEL_KEY])],
            'task_key' => ['required', Rule::in([YouTubeSummarizerService::TOOL_KEY])],
            'state' => ['required', 'array'],
            'state.transcript_languages' => ['required', 'array', 'min:1', 'max:5'],
            'state.transcript_languages.*' => ['required', 'string', 'distinct', 'regex:/^[a-z]{2,3}(?:-[A-Za-z]{2,4})?$/'],
            'state.summary_language' => ['required', 'string', 'max:80'],
            'state.summary_style' => ['required', 'string', 'max:500'],
            'state.max_summary_words' => ['required', 'integer', 'min:50', 'max:10000'],
            'state.extra_options' => ['nullable', 'array', 'max:20'],
            'state.extra_options.*' => ['string', 'max:150'],
            'state.last_output' => ['nullable', 'string', 'max:100000'],
        ];
    }

    private function backgroundRemoverRules(): array
    {
        return [
            'sub_tool_id' => ['required', 'integer', Rule::in([self::BACKGROUND_REMOVER_SUB_TOOL_ID])],
            'content' => ['nullable', 'string', 'max:5000'],
            'message' => ['nullable', 'string', 'max:5000'],
            'user_message' => ['required', 'string', 'max:5000'],
            'file' => ['required', 'file', 'max:20480', 'mimetypes:image/png,image/jpeg,image/webp,image/svg+xml'],
            'state' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    private function speechToTextRules(): array
    {
        return [
            'payload' => ['required', 'json', 'max:100000'],
            'sub_tool_id' => ['required', 'integer', Rule::in([self::SPEECH_TO_TEXT_SUB_TOOL_ID])],
            'content' => ['nullable', 'string', 'max:5000'],
            'message' => ['nullable', 'string', 'max:5000'],
            'user_message' => ['required', 'string', 'max:5000'],
            'file' => [
                'required',
                'file',
                'max:25600',
                'mimes:mp3,mp4,mpeg,mpga,m4a,wav,webm,ogg,flac',
            ],
            'state' => ['required', 'array'],
            'state.provider' => ['nullable', 'string', 'max:100'],
            'state.language' => ['required', 'string', 'regex:/^[a-z]{2,3}(?:-[A-Za-z]{2,4})?$/'],
            'state.extra_options' => ['nullable', 'array', 'max:20'],
            'state.extra_options.*' => ['string', 'max:150'],
            'state.last_output' => ['nullable', 'string', 'max:100000'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    private function imageUpscalerRules(): array
    {
        return [
            'sub_tool_id' => ['required', 'integer', Rule::in([self::IMAGE_UPSCALER_SUB_TOOL_ID])],
            'content' => ['nullable', 'string', 'max:5000'],
            'message' => ['nullable', 'string', 'max:5000'],
            'user_message' => ['required', 'string', 'max:5000'],
            'file' => ['required', 'file', 'max:20480', 'mimetypes:image/png,image/jpeg,image/webp,image/svg+xml'],
            'state' => ['nullable', 'array'],
            'state.scale' => ['required', 'numeric', 'min:1', 'max:4'],
            'state.face_enhance' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeMultipartPayload();
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
        $this->normalizeResumeBuilderStateForValidation();
        $this->normalizeBackgroundRemoverStateForValidation();
        $this->normalizeImageUpscalerStateForValidation();
        $this->normalizeImagePromptGeneratorStateForValidation();
        $this->normalizeYouTubeSummarizerStateForValidation();
    }

    private function normalizeMultipartPayload(): void
    {
        $payload = $this->input('payload');

        if (! is_string($payload) || trim($payload) === '') {
            return;
        }

        $decoded = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return;
        }

        $decodedSubToolId = (int) ($decoded['sub_tool_id'] ?? 0);
        $decodedToolKey = strtolower(trim((string) ($decoded['tool_key'] ?? $decoded['tool'] ?? '')));
        $decodedModelKey = strtolower(trim((string) ($decoded['model_key'] ?? '')));
        if (
            $decodedSubToolId !== self::SPEECH_TO_TEXT_SUB_TOOL_ID
            && $decodedToolKey !== SpeechToTextService::TOOL_KEY
            && $decodedModelKey !== SpeechToTextService::MODEL_KEY
        ) {
            return;
        }

        if ($this->user()) {
            $decoded['user_id'] = (int) $this->user()->id;
        }

        $this->merge($decoded);
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

    private function normalizeResumeBuilderStateForValidation(): void
    {
        if (! $this->isResumeBuilderRequest()) {
            return;
        }

        $state = $this->input('state');

        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        $this->merge([
            'sub_tool_id' => self::RESUME_BUILDER_SUB_TOOL_ID,
            'state' => is_array($state) ? $state : [],
        ]);
    }

    private function normalizeBackgroundRemoverStateForValidation(): void
    {
        if (! $this->isBackgroundRemoverRequest()) {
            return;
        }

        $state = $this->input('state');

        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        $this->merge(['state' => is_array($state) ? $state : []]);
    }

    private function normalizeImageUpscalerStateForValidation(): void
    {
        if (! $this->isImageUpscalerRequest()) {
            return;
        }

        $state = $this->input('state');

        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        $this->merge(['state' => is_array($state) ? $state : []]);
    }

    private function normalizeImagePromptGeneratorStateForValidation(): void
    {
        if (! $this->isImagePromptGeneratorRequest()) {
            return;
        }

        $state = $this->input('state');

        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        $this->merge([
            'sub_tool_id' => self::IMAGE_PROMPT_GENERATOR_SUB_TOOL_ID,
            'state' => is_array($state) ? $state : [],
        ]);
    }

    private function normalizeYouTubeSummarizerStateForValidation(): void
    {
        if (! $this->isYouTubeSummarizerRequest()) {
            return;
        }

        $state = $this->input('state');
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        $this->merge(['state' => is_array($state) ? $state : []]);
    }

    private function isResumeBuilderRequest(): bool
    {
        $toolKey = strtolower(trim((string) ($this->input('tool_key') ?: $this->input('tool'))));
        $modelKey = strtolower(trim((string) $this->input('model_key')));

        return (int) $this->input('sub_tool_id') === self::RESUME_BUILDER_SUB_TOOL_ID
            || $toolKey === 'resume_builder'
            || $modelKey === 'resume_builder';
    }

    private function isBackgroundRemoverRequest(): bool
    {
        $toolKey = strtolower(trim((string) ($this->input('tool_key') ?: $this->input('tool'))));
        $modelKey = strtolower(trim((string) $this->input('model_key')));

        return (int) $this->input('sub_tool_id') === self::BACKGROUND_REMOVER_SUB_TOOL_ID
            || $toolKey === BackgroundRemoverService::TOOL_KEY
            || $modelKey === 'background_remover';
    }

    private function isImageUpscalerRequest(): bool
    {
        $toolKey = strtolower(trim((string) ($this->input('tool_key') ?: $this->input('tool'))));
        $modelKey = strtolower(trim((string) $this->input('model_key')));

        return (int) $this->input('sub_tool_id') === self::IMAGE_UPSCALER_SUB_TOOL_ID
            || $toolKey === ImageUpscalerService::TOOL_KEY
            || $modelKey === 'image_upscaler';
    }

    private function isImagePromptGeneratorRequest(): bool
    {
        $toolKey = strtolower(trim((string) ($this->input('tool_key') ?: $this->input('tool'))));
        $modelKey = strtolower(trim((string) $this->input('model_key')));

        return (int) $this->input('sub_tool_id') === self::IMAGE_PROMPT_GENERATOR_SUB_TOOL_ID
            || $toolKey === ImagePromptGeneratorService::TOOL_KEY
            || $modelKey === ImagePromptGeneratorService::MODEL_KEY;
    }

    private function isYouTubeSummarizerRequest(): bool
    {
        $toolKey = strtolower(trim((string) ($this->input('tool_key') ?: $this->input('tool'))));
        $modelKey = strtolower(trim((string) $this->input('model_key')));

        return (int) $this->input('sub_tool_id') === self::YOUTUBE_SUMMARIZER_SUB_TOOL_ID
            || $toolKey === YouTubeSummarizerService::TOOL_KEY
            || $modelKey === YouTubeSummarizerService::MODEL_KEY;
    }

    private function isSpeechToTextRequest(): bool
    {
        $toolKey = strtolower(trim((string) ($this->input('tool_key') ?: $this->input('tool'))));
        $modelKey = strtolower(trim((string) $this->input('model_key')));

        return (int) $this->input('sub_tool_id') === self::SPEECH_TO_TEXT_SUB_TOOL_ID
            || $toolKey === SpeechToTextService::TOOL_KEY
            || $modelKey === SpeechToTextService::MODEL_KEY;
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

    private function resumeBuilderRules(): array
    {
        return [
            'sub_tool_id' => ['required', 'integer', 'in:'.self::RESUME_BUILDER_SUB_TOOL_ID],
            'content' => ['nullable', 'string', 'max:5000'],
            'message' => ['nullable', 'string', 'max:5000'],
            'user_message' => ['nullable', 'string', 'max:5000', 'required_without:file'],
            'state' => ['required', 'array'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'state.target_role' => ['required', 'string', 'max:255'],
            'state.candidate_name' => ['nullable', 'string', 'max:255'],
            'state.language' => ['required', 'string', 'max:80'],
            'state.tone' => ['required', 'string', 'max:80'],
            'state.experience_level' => ['required', 'string', 'max:80'],
            'state.resume_style' => ['required', 'string', 'max:120'],
            'state.output_format' => ['required', 'string', 'in:docx,pdf,text'],
            'state.sections_to_include' => ['required', 'array', 'min:1'],
            'state.sections_to_include.*' => ['string', 'max:100'],
            'state.extra_options' => ['nullable', 'array'],
            'state.extra_options.*' => ['string', 'max:150'],
            'state.last_output' => ['nullable', 'string', 'max:100000'],
            'state.previous_generated_file' => ['nullable', 'array'],
            'state.previous_generated_file.file_id' => ['nullable', 'string', 'max:255'],
            'state.previous_generated_file.download_url' => ['nullable', 'string', 'max:2048'],
            'state.previous_generated_file.filename' => ['nullable', 'string', 'max:255'],
            'state.previous_generated_file.content_type' => ['nullable', 'string', 'max:255'],
            'state.previous_generated_file.source' => ['nullable', 'string', 'max:80'],
        ];
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
