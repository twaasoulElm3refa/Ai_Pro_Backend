<?php

namespace App\Services;

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

        $state = $this->normalizeState($toolId, is_array($data['state'] ?? null) ? $data['state'] : [], $content);
        $state['last_output'] = null;
        $content = $this->contentForTool($toolId, $content, $state);

        if ($content === '') {
            throw ValidationException::withMessages([
                'user_message' => ['Message content is required.'],
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
            'user_message' => $content,
            'content' => $content,
            'tool' => $tool['tool_key'],
            'tool_key' => $tool['tool_key'],
            'model_key' => $tool['model_key'],
            'state' => $state,
            'debug' => (bool) ($data['debug'] ?? false),
            'idempotency_key' => $idempotencyKey,
        ];

        $userMessage = $this->storeUserMessage($conversation, $content, $idempotencyKey, [
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
        $normalizedResults = $this->normalizeResultItems($metadata['normalized_results'] ?? $metadata['results'] ?? []);
        $state = is_array($metadata['state'] ?? null)
            ? $this->normalizeState($toolId, $metadata['state'], '')
            : [];

        return [
            'success' => true,
            'type' => 'result',
            'sub_tool_id' => $toolId,
            'tool' => (string) ($metadata['tool'] ?? $tool['tool_key']),
            'tool_key' => (string) ($metadata['tool_key'] ?? $metadata['tool'] ?? $tool['tool_key']),
            'model_key' => (string) ($metadata['model_key'] ?? $tool['model_key']),
            'conversation_uuid' => $conversation->uuid,
            'message_id' => $assistantMessage->id,
            'assistant_message_id' => $assistantMessage->id,
            'state' => $state,
            'results' => $normalizedResults,
            'normalized_results' => $normalizedResults,
            'count' => count($normalizedResults),
            'message' => '',
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
            'body' => $this->buildUserPrompt($toolId, $state, $requestPayload['user_message']),
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
                'results_count' => null,
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
            default => [],
        };

        $arrayKeys = ['extra_options', 'checks', 'secondary_keywords'];
        $booleanKeys = ['include_long_tail', 'include_clusters', 'include_cta', 'include_recommendations', 'preserve_meaning', 'include_explanation'];
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
            $normalized['content'] = trim($content);
        }

        if ($toolId === self::KEYWORD_GENERATOR_SUB_TOOL_ID && ! $normalized['topic'] && trim($content) !== '') {
            $normalized['topic'] = trim($content);
        }

        return $normalized;
    }

    private function contentForTool(int $toolId, string $content, array $state): string
    {
        $content = trim($content);

        if ($content !== '') {
            return $content;
        }

        return trim((string) match ($toolId) {
            self::KEYWORD_GENERATOR_SUB_TOOL_ID => $state['topic'] ?? '',
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

            $text = $this->cleanOutputText($result['text'] ?? $result['content'] ?? $result['description'] ?? '');
            if ($text === '' || $this->looksLikeJson($text)) {
                continue;
            }

            $meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
            if ($toolId === self::META_DESCRIPTION_SUB_TOOL_ID) {
                $meta['characters'] = mb_strlen($text);
                $meta['max_characters'] = (int) ($state['max_characters'] ?? ($meta['max_characters'] ?? 160));
            }

            $normalized[] = [
                'id' => is_numeric($result['id'] ?? null) ? (int) $result['id'] : count($normalized) + 1,
                'text' => $text,
                'meta' => $meta,
            ];
        }

        return $normalized;
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
