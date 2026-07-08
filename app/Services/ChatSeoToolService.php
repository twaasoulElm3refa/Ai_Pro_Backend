<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use App\Models\Conversation;
use App\Models\CostLogger;
use App\Models\Message;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChatSeoToolService
{
    public const KEYWORD_GENERATOR_SUB_TOOL_ID = 13;
    public const META_DESCRIPTION_SUB_TOOL_ID = 14;
    public const CONTENT_ANALYZER_SUB_TOOL_ID = 15;
    public const CONTENT_OPTIMIZER_SUB_TOOL_ID = 16;
    public const AI_DETECTOR_SUB_TOOL_ID = 17;
    public const AI_HUMANIZER_SUB_TOOL_ID = 18;
    public const BUSINESS_NAME_GENERATOR_SUB_TOOL_ID = 20;

    private const TOOLS = [
        self::KEYWORD_GENERATOR_SUB_TOOL_ID => [
            'name' => 'Keyword Generator',
            'tool_key' => 'ai_keyword_generator',
            'model_key' => 'keyword_generator',
            'endpoint' => 'tasks/keyword-generator/chat',
        ],
        self::META_DESCRIPTION_SUB_TOOL_ID => [
            'name' => 'Meta Description Generator',
            'tool_key' => 'ai_meta_description_generator',
            'model_key' => 'meta_description_generator',
            'endpoint' => 'tasks/meta-description-generator/chat',
        ],
        self::CONTENT_ANALYZER_SUB_TOOL_ID => [
            'name' => 'Content Analyzer',
            'tool_key' => 'ai_content_analyzer',
            'model_key' => 'content_analyzer',
            'endpoint' => 'tasks/content-analyzer/chat',
        ],
        self::CONTENT_OPTIMIZER_SUB_TOOL_ID => [
            'name' => 'Content Optimizer',
            'tool_key' => 'ai_content_optimizer',
            'model_key' => 'content_optimizer',
            'endpoint' => 'tasks/content-optimizer/chat',
        ],
        self::AI_DETECTOR_SUB_TOOL_ID => [
            'name' => 'AI Detector',
            'tool_key' => 'ai_detector',
            'model_key' => 'ai_detector',
            'endpoint' => 'tasks/ai-detector/chat',
        ],
        self::AI_HUMANIZER_SUB_TOOL_ID => [
            'name' => 'AI Humanizer',
            'tool_key' => 'ai_humanizer',
            'model_key' => 'ai_humanizer',
            'endpoint' => 'tasks/ai-humanizer/chat',
        ],
        self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID => [
            'name' => 'Business Name Generator',
            'tool_key' => 'business_name_generator',
            'model_key' => 'business_name_generator',
            'endpoint' => 'tasks/business-name-generator/chat',
        ],
    ];

    public function __construct(
        private readonly AiArabicWriterService $writerService,
        private readonly ConversationMessageCacheService $messageCache
    ) {}

    public static function supports(int $subToolId, ?string $toolKey = null, ?string $modelKey = null): bool
    {
        if (isset(self::TOOLS[$subToolId])) {
            return true;
        }

        $toolKey = strtolower(trim((string) $toolKey));
        $modelKey = strtolower(trim((string) $modelKey));

        foreach (self::TOOLS as $config) {
            if ($toolKey !== '' && $toolKey === $config['tool_key']) {
                return true;
            }

            if ($modelKey !== '' && $modelKey === $config['model_key']) {
                return true;
            }
        }

        return false;
    }

    public function handle(Conversation $conversation, array $data, string $content, int $userId): array
    {
        $toolId = (int) ($data['sub_tool_id'] ?? $conversation->sub_tool_id ?? 0);
        $tool = self::TOOLS[$toolId] ?? null;

        if (! $tool) {
            throw ValidationException::withMessages([
                'sub_tool_id' => ['Unsupported SEO tool.'],
            ]);
        }

        if ((int) $conversation->user_id !== $userId) {
            abort(403, 'Conversation does not belong to the authenticated user.');
        }

        $originalContent = $content;
        $state = $this->normalizeState($toolId, is_array($data['state'] ?? null) ? $data['state'] : [], $content);
        $state['last_output'] = null;
        $content = $this->contentForTool($toolId, $content, $state);
        $chatMessageContent = in_array($toolId, [self::AI_DETECTOR_SUB_TOOL_ID, self::AI_HUMANIZER_SUB_TOOL_ID, self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID], true)
            ? trim($originalContent)
            : $content;

        if ($content === '') {
            throw ValidationException::withMessages([
                'user_message' => [
                    match ($toolId) {
                        self::AI_DETECTOR_SUB_TOOL_ID => 'Text to analyze is required.',
                        self::AI_HUMANIZER_SUB_TOOL_ID => 'Text to humanize is required.',
                        self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID => 'Business idea is required.',
                        default => 'Message content is required.',
                    },
                ],
            ]);
        }

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        $requestPayload = [
            'user_id' => $userId,
            'sub_tool_id' => $toolId,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => in_array($toolId, [self::AI_DETECTOR_SUB_TOOL_ID, self::AI_HUMANIZER_SUB_TOOL_ID, self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID], true) ? $chatMessageContent : $content,
            'content' => $content,
            'tool' => $tool['tool_key'],
            'tool_key' => $tool['tool_key'],
            'model_key' => $tool['model_key'],
            'state' => $state,
            'debug' => (bool) ($data['debug'] ?? false),
            'idempotency_key' => $idempotencyKey,
        ];

        $userMessage = $this->storeUserMessage($conversation, $chatMessageContent !== '' ? $chatMessageContent : $content, $idempotencyKey, [
            'type' => 'seo_tool_request',
            'tool' => $tool['tool_key'],
            'tool_key' => $tool['tool_key'],
            'model_key' => $tool['model_key'],
            'sub_tool_id' => $toolId,
            'conversation_uuid' => $conversation->uuid,
            'state' => $state,
            'request_payload' => $requestPayload,
        ]);

        $conversation->loadMissing('user.wallet', 'subTool');
        $existingAssistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('reply_to_message_id', $userMessage->id)
            ->first();

        if ($existingAssistant) {
            return $this->responseFromAssistant($existingAssistant, $conversation, $toolId, $userId);
        }

        $endpoint = $this->resolveEndpoint($conversation, $tool);
        $providerPayload = $this->buildProviderPayload($toolId, $tool, $conversation, $requestPayload, $state);

        $providerResponse = $this->writerService->generateReplyWithUsage($providerPayload, $endpoint);
        $providerResponse = is_array($providerResponse)
            ? $providerResponse
            : ['reply' => (string) $providerResponse];

        if ($this->providerResponseIsError($providerResponse)) {
            throw new AiServiceException(
                $this->providerErrorMessage($providerResponse),
                [
                    'endpoint' => $endpoint,
                    'tool' => $tool['tool_key'],
                    'model_key' => $tool['model_key'],
                    'sub_tool_id' => $toolId,
                    'provider_response' => $providerResponse,
                ]
            );
        }

        $rawOutput = $this->extractRawOutput($providerResponse);
        $normalizedResults = $this->normalizeResults($toolId, $providerResponse, $rawOutput, $state);

        if ($normalizedResults === []) {
            $fallbackText = $this->cleanOutputText($rawOutput);
            if ($fallbackText === '' || $this->looksLikeJson($fallbackText)) {
                throw ValidationException::withMessages([
                    'results' => ['The SEO tool response could not be formatted.'],
                ]);
            }

            $normalizedResults = [[
                'id' => 1,
                'text' => $fallbackText,
                'title' => null,
                'subject' => null,
                'meta' => [],
            ]];
        }

        $responseState = $state;
        $responseState['last_output'] = $this->resultsText($normalizedResults);
        $usage = $this->normalizeUsage($providerResponse['usage'] ?? data_get($providerResponse, 'raw.usage', []));
        $cost = $this->normalizeCost($providerResponse['cost'] ?? data_get($providerResponse, 'raw.cost', []));
        $tokensToDeduct = (int) ($usage['total_tokens'] ?? 0);
        $requestId = $this->toNullableString($providerResponse['request_id'] ?? data_get($providerResponse, 'raw.request_id'));
        $provider = $this->toNullableString($providerResponse['provider'] ?? data_get($providerResponse, 'raw.provider')) ?? 'openrouter';
        $modelKey = $this->toNullableString($providerResponse['model_key'] ?? data_get($providerResponse, 'raw.model_key')) ?? $tool['model_key'];

        $assistantMessage = null;
        DB::transaction(function () use (
            $conversation,
            $toolId,
            $tool,
            $provider,
            $modelKey,
            $requestId,
            $usage,
            $cost,
            $tokensToDeduct,
            $userMessage,
            $requestPayload,
            $responseState,
            $normalizedResults,
            $rawOutput,
            $userId,
            &$assistantMessage
        ): void {
            if ($tokensToDeduct > 0 && $conversation->user) {
                $this->deductWalletTokens($conversation->user, $tokensToDeduct);
            }

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $this->resultsText($normalizedResults),
                'is_error' => false,
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'type' => 'result',
                    'tool' => $tool['tool_key'],
                    'tool_key' => $tool['tool_key'],
                    'model_key' => $modelKey,
                    'provider' => $provider,
                    'request_id' => $requestId,
                    'sub_tool_id' => $toolId,
                    'conversation_uuid' => $conversation->uuid,
                    'state' => $responseState,
                    'request_payload' => $requestPayload,
                    'results' => $normalizedResults,
                    'normalized_results' => $normalizedResults,
                    'raw_output' => $rawOutput,
                    'usage' => $usage,
                    'cost' => $cost,
                    'tokens_deducted' => $tokensToDeduct,
                ],
            ]);

            if ($tokensToDeduct > 0 || array_sum(array_map('floatval', [
                $cost['input_cost'] ?? 0,
                $cost['output_cost'] ?? 0,
                $cost['web_search_cost'] ?? 0,
                $cost['total_cost'] ?? 0,
            ])) > 0) {
                CostLogger::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'sub_tool_id' => $toolId,
                    'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                    'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                    'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
                    'input_cost' => (float) ($cost['input_cost'] ?? 0),
                    'output_cost' => (float) ($cost['output_cost'] ?? 0),
                    'web_search_cost' => (float) ($cost['web_search_cost'] ?? 0),
                    'total_cost' => (float) ($cost['total_cost'] ?? 0),
                    'currency' => (string) ($cost['currency'] ?? 'USD'),
                    'provider_request_id' => $requestId,
                    'model_key' => $modelKey,
                ]);
            }
        });

        if (! $assistantMessage) {
            throw new \RuntimeException('Assistant message could not be saved.');
        }

        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        return $this->responseFromAssistant($assistantMessage, $conversation, $toolId, $userId);
    }

    private function storeUserMessage(Conversation $conversation, string $content, string $idempotencyKey, array $metadata): Message
    {
        $lock = Cache::lock("seo-message-send:{$conversation->user_id}:{$conversation->id}:{$idempotencyKey}", 15);

        $message = $lock->block(5, function () use ($conversation, $content, $idempotencyKey, $metadata) {
            return DB::transaction(function () use ($conversation, $content, $idempotencyKey, $metadata) {
                $existing = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    if (! is_array($existing->metadata ?? null)) {
                        $existing->metadata = $metadata;
                        $existing->save();
                    }

                    return $existing;
                }

                return Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $content,
                    'idempotency_key' => $idempotencyKey,
                    'is_error' => false,
                    'metadata' => $metadata,
                ]);
            }, 3);
        });

        $this->messageCache->updateAfterMessage($message);
        $this->clearCache((int) $conversation->user_id);

        return $message;
    }

    private function responseFromAssistant(Message $assistantMessage, Conversation $conversation, int $toolId, int $userId): array
    {
        $tool = self::TOOLS[$toolId];
        $metadata = is_array($assistantMessage->metadata ?? null) ? $assistantMessage->metadata : [];
        $state = is_array($metadata['state'] ?? null)
            ? $this->normalizeState($toolId, $metadata['state'], '')
            : [];
        $normalizedResults = $this->normalizeResultItems($metadata['normalized_results'] ?? $metadata['results'] ?? [], $toolId, $state);

        return [
            'success' => true,
            'type' => 'result',
            'sub_tool_id' => $toolId,
            'tool' => (string) ($metadata['tool'] ?? $tool['tool_key']),
            'tool_key' => (string) ($metadata['tool_key'] ?? $metadata['tool'] ?? $tool['tool_key']),
            'provider' => (string) ($metadata['provider'] ?? 'openrouter'),
            'model_key' => (string) ($metadata['model_key'] ?? $tool['model_key']),
            'user_id' => $userId,
            'conversation_uuid' => $conversation->uuid,
            'message_id' => $assistantMessage->id,
            'assistant_message_id' => $assistantMessage->id,
            'state' => $state,
            'results' => $normalizedResults,
            'normalized_results' => $normalizedResults,
            'count' => count($normalizedResults),
            'message' => match ($toolId) {
                self::AI_DETECTOR_SUB_TOOL_ID => 'AI content detection analysis completed successfully.',
                self::AI_HUMANIZER_SUB_TOOL_ID => 'تم تحويل النص إلى صياغة بشرية بنجاح.',
                self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID => 'تم توليد أسماء المشاريع بنجاح.',
                default => '',
            },
            'request_payload' => is_array($metadata['request_payload'] ?? null) ? $metadata['request_payload'] : null,
            'usage' => $this->normalizeUsage($metadata['usage'] ?? []),
            'cost' => $this->normalizeCost($metadata['cost'] ?? []),
            'wallet' => $this->walletSnapshot($userId),
        ];
    }

    private function buildProviderPayload(int $toolId, array $tool, Conversation $conversation, array $requestPayload, array $state): array
    {
        return [
            'user_id' => $requestPayload['user_id'],
            'sub_tool_id' => $toolId,
            'title' => $tool['name'],
            'conversation_uuid' => $conversation->uuid,
            'body' => $this->buildUserPrompt(
                $toolId,
                $state,
                in_array($toolId, [self::AI_DETECTOR_SUB_TOOL_ID, self::AI_HUMANIZER_SUB_TOOL_ID, self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID], true)
                    ? $requestPayload['content']
                    : $requestPayload['user_message']
            ),
            'user_message' => $requestPayload['user_message'],
            'content' => $requestPayload['content'],
            'tool' => $tool['tool_key'],
            'tool_key' => $tool['tool_key'],
            'model_key' => $tool['model_key'],
            'state' => $state,
            'system_prompt' => $this->buildSystemPrompt($toolId),
            'response_format' => 'results',
            'normalize_results' => true,
            'debug' => $requestPayload['debug'],
        ];
    }

    private function buildSystemPrompt(int $toolId): string
    {
        $common = 'Return valid JSON only. Use this exact schema: {"results":[{"id":1,"text":"...","meta":{}}]}. Do not wrap JSON in markdown fences. Do not include explanations outside JSON.';

        return match ($toolId) {
            self::KEYWORD_GENERATOR_SUB_TOOL_ID => $common.' Generate SEO keyword ideas. Each result text must contain only the keyword phrase. You may include meta.type, meta.intent, and meta.cluster.',
            self::META_DESCRIPTION_SUB_TOOL_ID => $common.' Generate concise SEO meta descriptions. Each result text must be a finished meta description. Include meta.characters and meta.max_characters.',
            self::CONTENT_ANALYZER_SUB_TOOL_ID => $common.' Analyze the content for SEO, readability, structure, keyword usage, and requested checks. Put the formatted readable analysis inside one result text.',
            self::CONTENT_OPTIMIZER_SUB_TOOL_ID => $common.' Optimize the content while preserving meaning. Put the improved text inside one result text.',
            self::AI_DETECTOR_SUB_TOOL_ID => <<<'PROMPT'
Return valid JSON only.
Do not include markdown.
Do not include explanations outside JSON.
You are an AI content detection assistant.

Analyze the provided text for possible AI-writing signals.
Be cautious and never claim certainty.
Do not say the text is definitely AI-written or definitely human-written.
Give a likelihood estimate only when include_score is true.
Use evidence based on style, structure, specificity, repetition, generic wording, and naturalness.
If include_rewrite_tips is true, include practical tips to make the text sound more natural.

Use this exact schema:
{
  "results": [
    {
      "id": 1,
      "text": "Final readable analysis here.",
      "meta": {
        "ai_likelihood_score": 55,
        "signals": ["signal 1", "signal 2"],
        "rewrite_tips": ["tip 1", "tip 2"]
      }
    }
  ]
}

Rules:
- ai_likelihood_score must be between 0 and 100.
- If include_score is false, omit ai_likelihood_score or set it to null.
- If include_evidence is false, return an empty signals array.
- If include_rewrite_tips is false, return an empty rewrite_tips array.
- results[0].text must be readable to the end user.
- Do not include chain-of-thought or hidden reasoning.
- Do not include phrases like "We need to" or "Let's analyze".
PROMPT,
            self::AI_HUMANIZER_SUB_TOOL_ID => <<<'PROMPT'
Return valid JSON only.
Do not include markdown.
Do not include explanations outside JSON.
You are an AI text humanizer.

Rewrite the provided content to sound more natural, human, smooth, and readable.
Preserve the original meaning if preserve_meaning is true.
Preserve important keywords if preserve_keywords is true.
Use the requested language, tone, audience, and humanize_level.
Avoid robotic phrasing, repetitive structure, stiff wording, and overly generic AI-like style.
Do not add unsupported facts.
Do not change names, numbers, or important terms unless necessary for readability.

Use this exact schema:
{
  "results": [
    {
      "id": 1,
      "text": "Humanized text here",
      "meta": {}
    }
  ]
}

Rules:
- Return exactly results_count results.
- Each result.text must contain only the rewritten/humanized text.
- No reasoning.
- No chain-of-thought.
- No explanation before or after JSON.
- The response must start with { and end with }.
PROMPT,
            self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID => <<<'PROMPT'
Return valid JSON only.
Do not include markdown.
Do not include explanations outside JSON.
You are a business name generator.

Generate business/project/startup name ideas based on:
- business_idea
- industry
- target_audience
- language
- tone
- name_style
- keywords
- avoid_words
- include_slogans
- include_domain_ideas
- results_count

Rules:
- Return exactly results_count results.
- Each result.text must contain only the business name.
- Names must be unique.
- Avoid avoid_words.
- Follow the requested language.
- Make names brandable, easy to remember, and suitable for the target audience.
- Do not add unsupported facts.
- No reasoning.
- No chain-of-thought.
- No explanation before or after JSON.
- The response must start with { and end with }.

Use this exact schema:
{
  "results": [
    {
      "id": 1,
      "text": "Business name here",
      "title": "Business name here",
      "subject": "Business name",
      "meta": {
        "slogan": "Optional slogan here",
        "domain_ideas": ["example.com", "example.ai"]
      }
    }
  ]
}

If include_slogans is false, set meta.slogan to null.
If include_domain_ideas is false, set meta.domain_ideas to [].
PROMPT,
            default => $common,
        };
    }

    private function buildUserPrompt(int $toolId, array $state, string $message): string
    {
        $lines = [
            'User message:',
            $message,
            '',
            'Options:',
        ];

        foreach ($state as $key => $value) {
            if ($key === 'last_output' || $value === null || $value === '' || $value === []) {
                continue;
            }

            $lines[] = '- '.$key.': '.(is_array($value) ? implode(', ', array_map('strval', $value)) : $this->boolString($value));
        }

        $lines[] = '';
        $lines[] = match ($toolId) {
            self::KEYWORD_GENERATOR_SUB_TOOL_ID => 'Generate the requested number of keyword ideas.',
            self::META_DESCRIPTION_SUB_TOOL_ID => 'Generate the requested number of meta descriptions and respect max_characters.',
            self::CONTENT_ANALYZER_SUB_TOOL_ID => 'Return a clear analysis in readable text.',
            self::CONTENT_OPTIMIZER_SUB_TOOL_ID => 'Return the optimized content only unless include_explanation is true.',
            self::AI_DETECTOR_SUB_TOOL_ID => 'Analyze the text for possible AI-writing signals and return one cautious readable detector analysis.',
            self::AI_HUMANIZER_SUB_TOOL_ID => 'Rewrite the text into a natural human style and return exactly the requested number of results.',
            self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID => 'Generate unique business names and return exactly the requested number of results.',
            default => 'Return clean results.',
        };

        return implode("\n", $lines);
    }

    private function normalizeState(int $toolId, array $state, string $content): array
    {
        $base = match ($toolId) {
            self::KEYWORD_GENERATOR_SUB_TOOL_ID => [
                'topic' => null,
                'industry' => null,
                'target_audience' => null,
                'language' => null,
                'keyword_type' => null,
                'search_intent' => null,
                'location' => null,
                'results_count' => 3,
                'include_long_tail' => false,
                'include_clusters' => false,
                'extra_options' => [],
                'last_output' => null,
            ],
            self::META_DESCRIPTION_SUB_TOOL_ID => [
                'content' => null,
                'page_title' => null,
                'primary_keyword' => null,
                'language' => 'Auto Detect',
                'tone' => 'Clear and persuasive',
                'length' => 'Standard',
                'max_characters' => 160,
                'include_cta' => false,
                'results_count' => 3,
                'extra_options' => ['SEO-friendly', 'Avoid keyword stuffing'],
                'last_output' => null,
            ],
            self::CONTENT_ANALYZER_SUB_TOOL_ID => [
                'content' => null,
                'analysis_goal' => 'SEO and readability analysis',
                'language' => 'Auto Detect',
                'target_keyword' => null,
                'content_type' => 'Article / Page Content',
                'audience' => 'General Audience',
                'checks' => ['SEO', 'Readability', 'Structure', 'Keyword usage', 'Search intent'],
                'detail_level' => 'Medium',
                'include_recommendations' => true,
                'extra_options' => ['Prioritize actionable fixes', 'Do not invent external metrics'],
                'last_output' => null,
            ],
            self::CONTENT_OPTIMIZER_SUB_TOOL_ID => [
                'content' => null,
                'optimization_goal' => 'Improve SEO, clarity, and readability',
                'primary_keyword' => null,
                'secondary_keywords' => [],
                'language' => 'Auto Detect',
                'tone' => 'Professional',
                'content_type' => 'Article / Page Content',
                'audience' => 'General Audience',
                'seo_level' => 'Balanced',
                'preserve_meaning' => true,
                'include_explanation' => false,
                'extra_options' => ['Natural keyword usage', 'Improve structure'],
                'last_output' => null,
            ],
            self::AI_DETECTOR_SUB_TOOL_ID => [
                'content' => null,
                'language' => 'Auto Detect',
                'analysis_depth' => 'Medium',
                'detection_focus' => 'AI writing signals',
                'include_score' => true,
                'include_evidence' => true,
                'include_rewrite_tips' => true,
                'extra_options' => ['Be cautious', 'Do not claim certainty'],
                'last_output' => null,
            ],
            self::AI_HUMANIZER_SUB_TOOL_ID => [
                'content' => null,
                'language' => 'Auto Detect',
                'tone' => 'Natural',
                'audience' => 'General Audience',
                'humanize_level' => 'Medium',
                'preserve_meaning' => true,
                'preserve_keywords' => true,
                'results_count' => 1,
                'extra_options' => ['Improve flow', 'Avoid robotic phrasing'],
                'last_output' => null,
            ],
            self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID => [
                'business_idea' => null,
                'industry' => null,
                'target_audience' => null,
                'language' => 'Auto Detect',
                'tone' => 'Creative',
                'name_style' => 'Brandable',
                'keywords' => [],
                'avoid_words' => [],
                'results_count' => 10,
                'include_slogans' => true,
                'include_domain_ideas' => true,
                'extra_options' => ['Easy to remember', 'Avoid duplicates', 'Brandable names'],
                'last_output' => null,
            ],
            default => [],
        };

        $arrayKeys = ['extra_options', 'checks', 'secondary_keywords', 'keywords', 'avoid_words'];
        $booleanKeys = [
            'include_long_tail',
            'include_clusters',
            'include_cta',
            'include_recommendations',
            'preserve_meaning',
            'include_explanation',
            'include_score',
            'include_evidence',
            'include_rewrite_tips',
            'preserve_keywords',
            'include_slogans',
            'include_domain_ideas',
        ];
        $integerKeys = ['results_count', 'max_characters'];
        $normalized = $base;

        foreach ($base as $key => $default) {
            $value = $state[$key] ?? $default;

            if (in_array($key, $arrayKeys, true)) {
                $normalized[$key] = is_array($value)
                    ? array_values(array_filter(array_map(fn ($item) => trim((string) $item), $value), fn ($item) => $item !== ''))
                    : (is_array($default) ? $default : []);
                continue;
            }

            if (in_array($key, $booleanKeys, true)) {
                $normalized[$key] = is_bool($value)
                    ? $value
                    : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $default;
                continue;
            }

            if (in_array($key, $integerKeys, true)) {
                $number = is_numeric($value) ? (int) $value : (is_numeric($default) ? (int) $default : null);
                $normalized[$key] = $number === null ? null : max(1, $number);
                continue;
            }

            $normalized[$key] = is_scalar($value) ? ($this->toNullableString($value) ?? null) : $default;
        }

        if (array_key_exists('content', $normalized) && ! $normalized['content'] && trim($content) !== '') {
            $normalized['content'] = match ($toolId) {
                self::AI_DETECTOR_SUB_TOOL_ID => $this->extractAiDetectorContent($content),
                self::AI_HUMANIZER_SUB_TOOL_ID => $this->extractAiHumanizerContent($content),
                default => trim($content),
            };
        }

        if ($toolId === self::KEYWORD_GENERATOR_SUB_TOOL_ID && ! $normalized['topic'] && trim($content) !== '') {
            $normalized['topic'] = trim($content);
        }

        if ($toolId === self::KEYWORD_GENERATOR_SUB_TOOL_ID) {
            $resultsCount = $normalized['results_count'] ?? 3;
            $normalized['results_count'] = is_numeric($resultsCount)
                ? max(1, (int) $resultsCount)
                : 3;
        }

        if ($toolId === self::AI_DETECTOR_SUB_TOOL_ID) {
            $normalized['content'] = $this->extractAiDetectorContent((string) ($normalized['content'] ?: $content));
            $normalized['extra_options'] = $normalized['extra_options'] !== []
                ? $normalized['extra_options']
                : ['Be cautious', 'Do not claim certainty'];
        }

        if ($toolId === self::AI_HUMANIZER_SUB_TOOL_ID) {
            $normalized['content'] = $this->extractAiHumanizerContent((string) ($normalized['content'] ?: $content));
            $isArabic = preg_match('/[\x{0600}-\x{06FF}]/u', (string) $normalized['content']) === 1;
            if (! $normalized['language'] || $normalized['language'] === 'Auto Detect') {
                $normalized['language'] = $isArabic ? 'Arabic' : 'Auto Detect';
            }
            $normalized['results_count'] = max(1, min(5, (int) ($normalized['results_count'] ?? 1)));
            $normalized['extra_options'] = $normalized['extra_options'] !== []
                ? $normalized['extra_options']
                : ['Improve flow', 'Avoid robotic phrasing'];
        }

        if ($toolId === self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID) {
            $normalized = $this->normalizeBusinessNameState($content, $normalized);
        }

        return $normalized;
    }

    private function extractAiDetectorContent(string $userMessage): string
    {
        $text = trim($userMessage);

        if ($text === '') {
            return '';
        }

        foreach ([
            '/AI-written:\s*([\s\S]+)$/iu',
            '/ai generated:\s*([\s\S]+)$/iu',
            '/content:\s*([\s\S]+)$/iu',
            '/text:\s*([\s\S]+)$/iu',
            '/النص:\s*([\s\S]+)$/iu',
            '/المحتوى:\s*([\s\S]+)$/iu',
        ] as $pattern) {
            if (preg_match($pattern, $text, $match) && trim((string) ($match[1] ?? '')) !== '') {
                return trim((string) $match[1]);
            }
        }

        return $text;
    }

    private function extractAiHumanizerContent(string $userMessage): string
    {
        $text = trim($userMessage);

        if ($text === '') {
            return '';
        }

        foreach ([
            '/Humanize this text in Arabic:\s*([\s\S]+)$/iu',
            '/Humanize this text:\s*([\s\S]+)$/iu',
            '/Humanize:\s*([\s\S]+)$/iu',
            '/content:\s*([\s\S]+)$/iu',
            '/text:\s*([\s\S]+)$/iu',
            '/أنسن هذا النص:\s*([\s\S]+)$/iu',
            '/حوّل هذا النص:\s*([\s\S]+)$/iu',
            '/حول هذا النص:\s*([\s\S]+)$/iu',
            '/النص:\s*([\s\S]+)$/iu',
            '/المحتوى:\s*([\s\S]+)$/iu',
        ] as $pattern) {
            if (preg_match($pattern, $text, $match) && trim((string) ($match[1] ?? '')) !== '') {
                return trim((string) $match[1]);
            }
        }

        return $text;
    }

    private function normalizeBusinessNameState(string $userMessage, array $state): array
    {
        $text = trim($userMessage);
        $businessIdea = $this->toNullableString($state['business_idea'] ?? null) ?? $this->extractBusinessIdea($text);

        preg_match('/(?:generate|write|create|اكتب|ولد|أنشئ)\s+(\d+)/iu', $text, $countMatch);

        $isArabic = preg_match('/arabic|عربي|العربية|بالعربي/iu', $text) === 1
            || preg_match('/[\x{0600}-\x{06FF}]/u', $text) === 1;

        $industry = $this->toNullableString($state['industry'] ?? null);
        if (! $industry && preg_match('/AI tools|artificial intelligence|ذكاء اصطناعي|أدوات ذكاء/iu', $text)) {
            $industry = 'AI tools';
        }

        $targetAudience = $this->toNullableString($state['target_audience'] ?? null);
        if (! $targetAudience && preg_match('/marketers|مسوقين|المسوقين/iu', $text)) {
            $targetAudience = 'marketers';
        }

        $language = $this->toNullableString($state['language'] ?? null);
        if (! $language || $language === 'Auto Detect') {
            $language = $isArabic ? 'Arabic' : 'Auto Detect';
        }

        $keywords = $this->normalizeStringList($state['keywords'] ?? []);
        if ($keywords === []) {
            preg_match_all('/\b[A-Za-z]{2,}\b/u', $text, $keywordMatches);
            $keywords = array_values(array_slice(array_unique($keywordMatches[0] ?? []), 0, 5));
        }

        return [
            'business_idea' => $businessIdea ?: $text,
            'industry' => $industry ?: 'General',
            'target_audience' => $targetAudience ?: 'General Audience',
            'language' => $language,
            'tone' => $this->toNullableString($state['tone'] ?? null) ?: 'Creative',
            'name_style' => $this->toNullableString($state['name_style'] ?? null) ?: 'Brandable',
            'keywords' => $keywords,
            'avoid_words' => $this->normalizeStringList($state['avoid_words'] ?? []),
            'results_count' => max(1, min(30, (int) ($state['results_count'] ?? ($countMatch[1] ?? 10)))),
            'include_slogans' => array_key_exists('include_slogans', $state)
                ? (bool) $state['include_slogans']
                : true,
            'include_domain_ideas' => array_key_exists('include_domain_ideas', $state)
                ? (bool) $state['include_domain_ideas']
                : true,
            'extra_options' => $this->normalizeStringList($state['extra_options'] ?? [])
                ?: ['Easy to remember', 'Avoid duplicates', 'Brandable names'],
            'last_output' => null,
        ];
    }

    private function extractBusinessIdea(string $userMessage): string
    {
        $text = trim($userMessage);

        if ($text === '') {
            return '';
        }

        foreach ([
            '/business names?\s+for\s+(?:a\s+|an\s+)?([\s\S]+)$/iu',
            '/project names?\s+for\s+(?:a\s+|an\s+)?([\s\S]+)$/iu',
            '/startup names?\s+for\s+(?:a\s+|an\s+)?([\s\S]+)$/iu',
            '/names?\s+for\s+(?:a\s+|an\s+)?([\s\S]+)$/iu',
            '/(?:أسماء|اسماء)\s+(?:مشاريع|مشروع|شركات|شركة)\s+(?:عن|لـ|ل)?\s*([\s\S]+)$/iu',
            '/فكرة\s+المشروع:\s*([\s\S]+)$/iu',
            '/business idea:\s*([\s\S]+)$/iu',
        ] as $pattern) {
            if (preg_match($pattern, $text, $match) && trim((string) ($match[1] ?? '')) !== '') {
                return trim((string) $match[1]);
            }
        }

        return $text;
    }

    private function providerResponseIsError(array $providerResponse): bool
    {
        $raw = is_array($providerResponse['raw'] ?? null) ? $providerResponse['raw'] : [];
        $success = $providerResponse['success']
            ?? ($raw['success'] ?? ($raw['data']['success'] ?? true));
        $type = strtolower(trim((string) (
            $providerResponse['type']
            ?? ($raw['type'] ?? ($raw['data']['type'] ?? ''))
        )));

        return $success === false || $type === 'error';
    }

    private function providerErrorMessage(array $providerResponse): string
    {
        $raw = is_array($providerResponse['raw'] ?? null) ? $providerResponse['raw'] : [];
        $status = (int) (
            $providerResponse['status']
            ?? ($raw['status'] ?? ($raw['data']['status'] ?? 0))
        );
        $message = $this->toNullableString(
            $providerResponse['message']
            ?? ($providerResponse['reply'] ?? null)
            ?? ($raw['message'] ?? null)
            ?? ($raw['data']['message'] ?? null)
            ?? ($raw['error'] ?? null)
            ?? ($raw['data']['error'] ?? null)
        );

        $haystack = strtolower((string) $message);

        if (
            $status === 429
            || str_contains($haystack, '429')
            || str_contains($haystack, 'rate limit')
            || str_contains($haystack, 'rate-limited')
            || str_contains($haystack, 'too many requests')
        ) {
            return 'الموديل مشغول مؤقتا بسبب كثرة الطلبات. يرجى المحاولة بعد قليل.';
        }

        if (
            str_contains($haystack, 'openrouter')
            || str_contains($haystack, 'provider returned error')
            || str_contains($haystack, 'provider error')
        ) {
            return 'A temporary error occurred while connecting to the AI provider. Please try again.';
        }

        return $message && ! $this->looksTechnicalError($message)
            ? $message
            : 'AI provider failed while generating the response. Please try again.';
    }

    private function looksTechnicalError(string $message): bool
    {
        $haystack = strtolower($message);

        return str_contains($haystack, '{')
            || str_contains($haystack, 'http://')
            || str_contains($haystack, 'https://')
            || str_contains($haystack, 'stack trace')
            || str_contains($haystack, 'request_id')
            || str_contains($haystack, 'provider')
            || str_contains($haystack, 'openrouter');
    }

    private function contentForTool(int $toolId, string $content, array $state): string
    {
        $content = trim($content);

        if ($toolId === self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID) {
            return trim((string) ($state['business_idea'] ?? $content));
        }

        if (in_array($toolId, [self::AI_DETECTOR_SUB_TOOL_ID, self::AI_HUMANIZER_SUB_TOOL_ID], true)) {
            return trim((string) ($state['content'] ?? $content));
        }

        if ($content !== '') {
            return $content;
        }

        return trim((string) match ($toolId) {
            self::KEYWORD_GENERATOR_SUB_TOOL_ID => $state['topic'] ?? '',
            self::AI_DETECTOR_SUB_TOOL_ID => $state['content'] ?? '',
            self::AI_HUMANIZER_SUB_TOOL_ID => $state['content'] ?? '',
            self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID => $state['business_idea'] ?? '',
            default => $state['content'] ?? '',
        });
    }

    private function normalizeResults(int $toolId, mixed $providerResponse, string $rawOutput, array $state): array
    {
        $candidates = [
            data_get($providerResponse, 'normalized_results'),
            data_get($providerResponse, 'results'),
            data_get($providerResponse, 'raw.normalized_results'),
            data_get($providerResponse, 'raw.results'),
            data_get($providerResponse, 'raw.data.normalized_results'),
            data_get($providerResponse, 'raw.data.results'),
            $this->decodeJsonLike($rawOutput),
        ];

        foreach ($candidates as $candidate) {
            $results = $this->extractResults($candidate);
            $normalized = $this->normalizeResultItems($results, $toolId, $state);

            if ($normalized !== []) {
                return $normalized;
            }
        }

        return [];
    }

    private function extractResults(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (is_array($value['results'] ?? null)) {
            return $value['results'];
        }

        if (is_array($value['normalized_results'] ?? null)) {
            return $value['normalized_results'];
        }

        if (array_is_list($value)) {
            return $value;
        }

        return [];
    }

    private function normalizeResultItems(mixed $results, ?int $toolId = null, array $state = []): array
    {
        if (! is_array($results)) {
            return [];
        }

        $normalized = [];

        foreach ($results as $index => $result) {
            if (is_string($result)) {
                $decoded = $this->decodeJsonLike($result);
                if (is_array($decoded)) {
                    $nested = $this->normalizeResultItems($this->extractResults($decoded), $toolId, $state);
                    array_push($normalized, ...$nested);
                    continue;
                }

                $result = ['text' => $result];
            }

            if (! is_array($result)) {
                continue;
            }

            $text = $this->cleanOutputText($result['text'] ?? $result['name'] ?? $result['content'] ?? $result['description'] ?? '');
            if ($text === '' || $this->looksLikeJson($text)) {
                continue;
            }

            $meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
            if ($toolId === self::META_DESCRIPTION_SUB_TOOL_ID) {
                $meta['characters'] = mb_strlen($text);
                $meta['max_characters'] = (int) ($state['max_characters'] ?? ($meta['max_characters'] ?? 160));
            }

            if ($toolId === self::AI_DETECTOR_SUB_TOOL_ID) {
                $meta = $this->normalizeAiDetectorMeta($meta, $state);
            }

            if ($toolId === self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID) {
                $meta = $this->normalizeBusinessNameMeta($meta, $state);
            }

            $title = $this->toNullableString($result['title'] ?? null) ?? ($toolId === self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID ? $text : null);
            $subject = $this->toNullableString($result['subject'] ?? null) ?? ($toolId === self::BUSINESS_NAME_GENERATOR_SUB_TOOL_ID ? 'Business name' : null);
            if ($toolId === self::KEYWORD_GENERATOR_SUB_TOOL_ID) {
                $title = null;
                $subject = null;
            }

            $item = [
                'id' => is_numeric($result['id'] ?? null) ? (int) $result['id'] : count($normalized) + 1,
                'text' => $text,
                'meta' => $meta,
            ];

            if ($title !== null) {
                $item['title'] = $title;
            }

            if ($subject !== null) {
                $item['subject'] = $subject;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    private function normalizeAiDetectorMeta(array $meta, array $state): array
    {
        if (($state['include_score'] ?? true) && array_key_exists('ai_likelihood_score', $meta)) {
            $score = is_numeric($meta['ai_likelihood_score']) ? (int) $meta['ai_likelihood_score'] : null;
            $meta['ai_likelihood_score'] = $score === null ? null : max(0, min(100, $score));
        } elseif (! ($state['include_score'] ?? true)) {
            unset($meta['ai_likelihood_score']);
        }

        $meta['signals'] = ($state['include_evidence'] ?? true)
            ? $this->normalizeStringList($meta['signals'] ?? [])
            : [];

        $meta['rewrite_tips'] = ($state['include_rewrite_tips'] ?? true)
            ? $this->normalizeStringList($meta['rewrite_tips'] ?? [])
            : [];

        return $meta;
    }

    private function normalizeBusinessNameMeta(array $meta, array $state): array
    {
        $meta['slogan'] = ($state['include_slogans'] ?? true)
            ? $this->toNullableString($meta['slogan'] ?? null)
            : null;

        $meta['domain_ideas'] = ($state['include_domain_ideas'] ?? true)
            ? $this->normalizeStringList($meta['domain_ideas'] ?? [])
            : [];

        return $meta;
    }

    private function normalizeStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r?\n|,/u', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($item) => trim((string) $item), $value),
            fn ($item) => $item !== ''
        ));
    }

    private function extractRawOutput(mixed $providerResponse): string
    {
        $candidates = [
            data_get($providerResponse, 'reply'),
            data_get($providerResponse, 'message'),
            data_get($providerResponse, 'content'),
            data_get($providerResponse, 'raw.reply'),
            data_get($providerResponse, 'raw.message'),
            data_get($providerResponse, 'raw.content'),
            data_get($providerResponse, 'raw.data.reply'),
            data_get($providerResponse, 'raw.data.message'),
            data_get($providerResponse, 'raw.data.content'),
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        return '';
    }

    private function decodeJsonLike(string $value): mixed
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/^```(?:json)?\s*/iu', '', $value) ?? $value;
        $value = preg_replace('/\s*```$/u', '', $value) ?? $value;
        $decoded = json_decode(trim($value), true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function cleanOutputText(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $text = trim((string) $value);
        $text = preg_replace('/^```(?:json|markdown)?\s*/iu', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/u', '', $text) ?? $text;

        if (! str_contains($text, "\n") && preg_match('/\\\\[nr]/u', $text) === 1) {
            $text = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $text);
        }

        return trim(str_replace('\\"', '"', $text));
    }

    private function resultsText(array $results): string
    {
        return collect($results)
            ->map(fn (array $result) => trim((string) ($result['text'] ?? '')))
            ->filter()
            ->implode("\n\n");
    }

    private function resolveEndpoint(Conversation $conversation, array $tool): string
    {
        $conversation->loadMissing('subTool');
        $config = is_array($conversation->subTool?->config ?? null) ? $conversation->subTool->config : [];

        return trim((string) ($config['endpoint'] ?? ''))
            ?: (trim((string) ($conversation->subTool?->endpoint ?? '')) ?: $tool['endpoint']);
    }

    private function normalizeUsage(mixed $usage): array
    {
        $usage = is_array($usage) ? $usage : [];
        $input = (int) ($usage['input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? ($input + $output));

        return [
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => $total,
        ];
    }

    private function normalizeCost(mixed $cost): array
    {
        $cost = is_array($cost) ? $cost : [];

        return [
            'input_cost' => (float) ($cost['input_cost'] ?? 0),
            'output_cost' => (float) ($cost['output_cost'] ?? 0),
            'web_search_cost' => (float) ($cost['web_search_cost'] ?? 0),
            'total_cost' => (float) ($cost['total_cost'] ?? 0),
            'currency' => strtoupper(trim((string) ($cost['currency'] ?? 'USD')) ?: 'USD'),
        ];
    }

    private function deductWalletTokens(User $user, int $tokens): void
    {
        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

        if (! $wallet) {
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'uuid' => (string) Str::uuid(),
                'balance' => 0,
                'ip_address' => request()->ip(),
            ]);
        }

        $wallet->balance = max(0, (int) $wallet->balance - $tokens);
        $wallet->save();
    }

    private function walletSnapshot(int $userId): array
    {
        $wallet = Wallet::where('user_id', $userId)->first();

        return [
            'balance' => $wallet ? (int) $wallet->balance : null,
            'payback_balance' => $wallet ? (int) ($wallet->payback_balance ?? 0) : null,
        ];
    }

    private function clearCache(int $userId): void
    {
        try {
            Cache::tags(['conversations', "user_{$userId}"])->flush();
        } catch (Throwable $th) {
            Log::debug('Conversation tagged cache flush skipped.', [
                'error' => $th->getMessage(),
            ]);
        }
    }

    private function toNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function boolString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function looksLikeJson(string $value): bool
    {
        $value = ltrim($value);

        return str_starts_with($value, '{') || str_starts_with($value, '[');
    }
}
