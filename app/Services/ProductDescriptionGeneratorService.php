<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\CostLogger;
use App\Models\Message;
use App\Models\User;
use App\Models\Wallet;
use App\Repository\Messages\MessageInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProductDescriptionGeneratorService
{
    public const SUB_TOOL_ID = 8;
    public const TOOL_KEY = 'ai_product_description_generator';
    public const MODEL_KEY = 'product_description_generator';
    public const ENDPOINT = 'tasks/product-description-generator/chat';

    private const MODEL_PROMPT = <<<'PROMPT'
You are a professional AI Product Description Generator.

Create one polished, ready-to-publish product description using the supplied product, brand, features, target audience, language, tone, length, and platform. Emphasize customer benefits without inventing unsupported specifications. When the language is Arabic, write clear natural Arabic without unnecessary language mixing.

When include_bullets is true, add a concise feature or benefit list. When include_seo_keywords is true, weave relevant search phrases naturally into the copy without keyword stuffing. Apply extra_options when present. Preserve readable paragraphs and bullet formatting. Return the final description in results[0].text.
PROMPT;

    public function __construct(
        private readonly AiArabicWriterService $writerService,
        private readonly MessageInterface $message,
        private readonly ConversationMessageCacheService $messageCache
    ) {
    }

    public function handle(
        Conversation $conversation,
        array $data,
        string $content,
        int $userId
    ): array {
        $requestState = $this->normalizeState($data['state'] ?? []);
        $latestState = $this->resolveLatestState($conversation);
        $state = $this->mergeState($latestState, $requestState);
        $state = $this->inferStateFromContent($content, $state);

        Log::info('ProductDescriptionGenerator request received', [
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => $content,
            'request_state' => $requestState,
            'state' => $state,
        ]);

        [$userMessage, $wasCreated] = $this->persistUserMessage(
            $conversation,
            $content,
            $state,
            $data['idempotency_key'] ?? null
        );

        $conversation->loadMissing('user.wallet', 'subTool');
        if (! $conversation->user) {
            throw new \RuntimeException('Conversation user not found.');
        }

        $userMessage->loadMissing('conversation.subTool');
        $this->messageCache->updateAfterMessage($userMessage);
        $this->clearCache($userId);

        $existingAssistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('reply_to_message_id', $userMessage->id)
            ->first();

        if ($existingAssistant) {
            return $this->buildResponseFromAssistant(
                $existingAssistant,
                $conversation,
                $userId
            ) + ['was_created' => false];
        }

        $payload = [
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'conversation_id' => $conversation->id,
            'content' => $content,
            'user_message' => $content,
            'role' => 'user',
            'tool' => self::TOOL_KEY,
            'model_key' => self::MODEL_KEY,
            'state' => $state,
            'system_prompt' => self::MODEL_PROMPT,
            'debug' => (bool) ($data['debug'] ?? false),
        ];

        $providerError = null;

        try {
            $providerResponse = $this->writerService->generateReplyWithUsage(
                $payload,
                self::ENDPOINT
            );
        } catch (Throwable $th) {
            $providerError = $th;

            Log::error('ProductDescriptionGenerator failed', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
            ]);

            $errorMessage = (bool) ($data['debug'] ?? false)
                ? $th->getMessage()
                : 'حدث خطأ أثناء توليد وصف المنتج. حاول مرة أخرى.';

            $providerResponse = [
                'reply' => $errorMessage,
                'provider' => 'openrouter',
                'model_key' => self::MODEL_KEY,
                'raw' => [
                    'success' => false,
                    'type' => 'error',
                    'tool' => self::TOOL_KEY,
                    'provider' => 'openrouter',
                    'model_key' => self::MODEL_KEY,
                    'message' => $errorMessage,
                ],
            ];
        }

        $providerResponse = is_array($providerResponse)
            ? $providerResponse
            : ['reply' => (string) $providerResponse];
        $raw = is_array($providerResponse['raw'] ?? null) ? $providerResponse['raw'] : [];
        $providerSuccess = (bool) ($raw['success'] ?? ($raw['data']['success'] ?? true));
        $responseState = $this->normalizeState(
            $providerResponse['state'] ?? ($raw['state'] ?? ($raw['data']['state'] ?? []))
        );
        $mergedState = $this->mergeState($state, $responseState);
        $results = $this->normalizeResults(
            $providerResponse['results']
                ?? ($raw['results'] ?? ($raw['data']['results'] ?? []))
        );
        $type = strtolower(trim((string) (
            $providerResponse['type'] ?? ($raw['type'] ?? ($raw['data']['type'] ?? ''))
        )));

        if (! $providerSuccess) {
            $type = 'error';
        } elseif (count($results) > 0) {
            $type = 'result';
        } elseif ($type === 'question' || count($this->getMissingFields($mergedState)) > 0) {
            $type = 'question';
            $results = [];
        } else {
            $type = 'error';
        }

        if ($type === 'result') {
            $mergedState['last_output'] = $this->buildOutput($results);
        }

        $responseMessage = trim((string) (
            $raw['message']
            ?? ($raw['data']['message'] ?? ($providerResponse['reply'] ?? ''))
        ));

        if ($responseMessage === '') {
            $responseMessage = match ($type) {
                'question' => 'يرجى إدخال اسم المنتج أو وصف المنتج أولًا.',
                'result' => 'تم توليد وصف المنتج بنجاح.',
                default => 'حدث خطأ أثناء توليد وصف المنتج. حاول مرة أخرى.',
            };
        }

        $rawUsage = $raw['usage'] ?? ($providerResponse['usage'] ?? null);
        $rawCost = $raw['cost'] ?? ($providerResponse['cost'] ?? null);
        $usage = $this->normalizeUsage($rawUsage);
        $cost = $this->normalizeCost($rawCost);
        $usageMetadata = is_array($rawUsage) && count($rawUsage) > 0 ? $usage : null;
        $costMetadata = is_array($rawCost) && count($rawCost) > 0 ? $cost : null;
        $provider = $this->toNullableString(
            $providerResponse['provider'] ?? ($raw['provider'] ?? ($raw['data']['provider'] ?? null))
        ) ?? 'openrouter';
        $requestId = $this->toNullableString(
            $providerResponse['request_id'] ?? ($raw['request_id'] ?? ($raw['data']['request_id'] ?? null))
        );
        $count = $type === 'result' ? count($results) : 0;
        $assistantContent = $type === 'result' ? $this->buildOutput($results) : $responseMessage;
        $tokensToDeduct = $type === 'result' ? (int) $usage['total_tokens'] : 0;
        $walletSnapshot = ['balance' => null, 'payback_balance' => null];
        $assistantMessage = null;

        DB::transaction(function () use (
            $conversation,
            $userMessage,
            $userId,
            $type,
            $assistantContent,
            $provider,
            $requestId,
            $mergedState,
            $results,
            $responseMessage,
            $count,
            $usage,
            $cost,
            $usageMetadata,
            $costMetadata,
            $tokensToDeduct,
            $providerError,
            &$walletSnapshot,
            &$assistantMessage
        ): void {
            if ($type === 'result') {
                $walletSnapshot = $this->deductWalletTokens(
                    $conversation->user,
                    $tokensToDeduct
                );
            } else {
                $wallet = Wallet::where('user_id', $conversation->user_id)
                    ->lockForUpdate()
                    ->first();
                $walletSnapshot = [
                    'balance' => $wallet ? (int) $wallet->balance : null,
                    'payback_balance' => $wallet ? (int) ($wallet->payback_balance ?? 0) : null,
                ];
            }

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $assistantContent,
                'is_error' => $type === 'error',
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'type' => $type,
                    'tool' => self::TOOL_KEY,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'provider' => $provider,
                    'model_key' => self::MODEL_KEY,
                    'state' => $mergedState,
                    'results' => $results,
                    'count' => $count,
                    'request_id' => $requestId,
                    'usage' => $usageMetadata,
                    'cost' => $costMetadata,
                    'message' => $responseMessage,
                    'tokens_deducted' => $tokensToDeduct,
                    'debug_error' => $providerError?->getMessage(),
                    'conversation_uuid' => $conversation->uuid,
                ],
            ]);

            if ($type === 'result') {
                CostLogger::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'input_tokens' => $usage['input_tokens'],
                    'output_tokens' => $usage['output_tokens'],
                    'total_tokens' => $usage['total_tokens'],
                    'input_cost' => $cost['input_cost'],
                    'output_cost' => $cost['output_cost'],
                    'web_search_cost' => $cost['web_search_cost'],
                    'total_cost' => $cost['total_cost'],
                    'currency' => $cost['currency'],
                    'provider_request_id' => $requestId,
                    'model_key' => self::MODEL_KEY,
                ]);
            }
        });

        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        return [
            'success' => $type !== 'error',
            'type' => $type,
            'tool' => self::TOOL_KEY,
            'provider' => $provider,
            'model_key' => self::MODEL_KEY,
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'message' => $responseMessage,
            'state' => $mergedState,
            'results' => $results,
            'count' => $count,
            'request_id' => $requestId,
            'debug' => (bool) ($data['debug'] ?? false) && $providerError
                ? ['error' => $providerError->getMessage()]
                : null,
            'usage' => $usageMetadata,
            'cost' => $costMetadata,
            'tokens_deducted' => $tokensToDeduct,
            'wallet' => $walletSnapshot,
            'assistant_message_id' => $assistantMessage->id,
            'was_created' => $wasCreated,
        ];
    }

    public function normalizeState(mixed $state): array
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = is_array($decoded) ? $decoded : [];
        }

        $state = is_array($state) ? $state : [];
        $merged = array_merge([
            'product' => null,
            'brand_name' => null,
            'product_features' => null,
            'target_audience' => null,
            'language' => null,
            'tone' => null,
            'length' => null,
            'platform' => null,
            'include_bullets' => null,
            'include_seo_keywords' => null,
            'extra_options' => [],
            'last_output' => null,
        ], $state);

        foreach ([
            'product',
            'brand_name',
            'product_features',
            'target_audience',
            'language',
            'tone',
            'length',
            'platform',
            'last_output',
        ] as $key) {
            $merged[$key] = $this->toNullableString($merged[$key] ?? null);
        }

        $merged['include_bullets'] = $this->toNullableBoolean(
            $merged['include_bullets'] ?? null
        );
        $merged['include_seo_keywords'] = $this->toNullableBoolean(
            $merged['include_seo_keywords'] ?? null
        );

        $extraOptions = is_array($merged['extra_options'] ?? null)
            ? $merged['extra_options']
            : [];
        $merged['extra_options'] = collect($extraOptions)
            ->map(fn ($item) => $this->toNullableString($item))
            ->filter()
            ->values()
            ->all();

        return $merged;
    }

    private function inferStateFromContent(string $content, array $currentState): array
    {
        $state = $this->normalizeState($currentState);
        $text = mb_strtolower(trim($content));
        $isArabic = preg_match('/\p{Arabic}/u', $content) === 1;
        if ($state['product'] === null) {
            $state['product'] = $this->extractProduct($content);
        }

        if ($state['product_features'] === null) {
            $state['product_features'] = $this->extractFeatures($content);
        }

        $state['target_audience'] ??= 'General Customers';
        $state['language'] ??= $isArabic ? 'Arabic' : 'English';
        $state['tone'] ??= $this->containsAny($text, ['professional', 'احترافي', 'احترافية'])
            ? 'Professional'
            : 'Marketing';
        $state['length'] ??= 'Medium';
        $state['platform'] ??= 'Website / Store';
        $state['include_bullets'] ??= true;
        $state['include_seo_keywords'] ??= true;

        if (count($state['extra_options']) === 0) {
            $state['extra_options'] = ['Benefit-focused', 'Clear and persuasive'];
        }

        return $this->normalizeState($state);
    }

    private function extractProduct(string $content): ?string
    {
        $patterns = [
            '/(?:احترافي\s+)?ل([^،,.]+?)(?=\s+(?:تدعم|تتميز|مزودة|بميزات)|[،,.]|$)/u',
            '/(?:لـ|عن|لمنتج|المنتج)\s+([^،,.]+)(?:[،,.]|$)/u',
            '/(?:for|product)\s+(?:an?\s+)?([^,.;]+)(?:[,.;]|$)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($content), $matches) === 1) {
                return $this->toNullableString($matches[1] ?? null);
            }
        }

        $cleaned = preg_replace(
            '/^(?:اكتب|أنشئ|ولد|ولّد|write|create|generate)\s+(?:لي\s+)?(?:وصف(?:ا|ًا)?\s+)?(?:منتج\s+)?(?:احترافي\s+)?/iu',
            '',
            trim($content)
        );

        return $this->toNullableString(mb_substr((string) $cleaned, 0, 180));
    }

    private function extractFeatures(string $content): ?string
    {
        $patterns = [
            '/(?:تدعم|يتميز بـ|مميزاته|بمميزات)\s+(.+)$/u',
            '/(?:features?|with)\s+(.+)$/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($content), $matches) === 1) {
                return $this->toNullableString($matches[1] ?? null);
            }
        }

        return null;
    }

    private function toNullableBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [1, '1', 'true'], true)) {
            return true;
        }

        if (in_array($value, [0, '0', 'false'], true)) {
            return false;
        }

        return null;
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function persistUserMessage(
        Conversation $conversation,
        string $content,
        array $state,
        mixed $requestedIdempotencyKey
    ): array {
        $idempotencyKey = $this->toNullableString($requestedIdempotencyKey) ?? (string) Str::uuid();
        $lock = Cache::lock(
            "message-send:{$conversation->user_id}:{$conversation->id}:{$idempotencyKey}",
            15
        );

        return $lock->block(5, function () use (
            $conversation,
            $content,
            $state,
            $idempotencyKey
        ): array {
            return DB::transaction(function () use (
                $conversation,
                $content,
                $state,
                $idempotencyKey
            ): array {
                $existing = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where(function ($query) use ($idempotencyKey, $content): void {
                        $query->where('idempotency_key', $idempotencyKey)
                            ->orWhere(function ($duplicate) use ($content): void {
                                $duplicate->where('content', $content)
                                    ->where('created_at', '>=', now()->subSeconds(20));
                            });
                    })
                    ->orderByDesc('id')
                    ->first();

                if ($existing) {
                    return [$existing, false];
                }

                $userMessage = $this->message->send([
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $content,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => [
                        'type' => 'user_input',
                        'tool' => self::TOOL_KEY,
                        'model_key' => self::MODEL_KEY,
                        'sub_tool_id' => self::SUB_TOOL_ID,
                        'conversation_uuid' => $conversation->uuid,
                        'state' => $state,
                    ],
                ]);

                if (! $userMessage instanceof Message) {
                    throw new \RuntimeException('Message could not be saved.');
                }

                return [$userMessage, true];
            }, 3);
        });
    }

    private function resolveLatestState(Conversation $conversation): array
    {
        $message = Message::where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('metadata')
            ->orderByDesc('id')
            ->get()
            ->first(function (Message $message): bool {
                $metadata = is_array($message->metadata ?? null) ? $message->metadata : [];

                return (
                    strtolower((string) ($metadata['tool'] ?? '')) === self::TOOL_KEY
                    || (int) ($metadata['sub_tool_id'] ?? 0) === self::SUB_TOOL_ID
                ) && is_array($metadata['state'] ?? null);
            });

        $metadata = $message && is_array($message->metadata ?? null)
            ? $message->metadata
            : [];

        return $this->normalizeState($metadata['state'] ?? []);
    }

    private function mergeState(array $oldState, array $newState): array
    {
        $merged = $this->normalizeState($oldState);
        $incoming = $this->normalizeState($newState);

        foreach ($incoming as $key => $value) {
            if ($key === 'extra_options') {
                if (count($value) > 0) {
                    $merged[$key] = $value;
                }
                continue;
            }

            if ($value !== null && $value !== '') {
                $merged[$key] = $value;
            }
        }

        return $this->normalizeState($merged);
    }

    private function getMissingFields(array $state): array
    {
        $state = $this->normalizeState($state);
        $required = ['product'];

        return collect($required)
            ->filter(fn (string $key): bool => $state[$key] === null || $state[$key] === '')
            ->values()
            ->all();
    }

    private function normalizeResults(mixed $results): array
    {
        if (! is_array($results)) {
            return [];
        }

        return collect($results)
            ->map(function ($result, int $index): array {
                $row = is_array($result) ? $result : [];

                return [
                    'id' => isset($row['id']) && is_numeric($row['id'])
                        ? (int) $row['id']
                        : $index + 1,
                    'text' => trim((string) ($row['text'] ?? (is_scalar($result) ? $result : ''))),
                    'title' => $this->toNullableString($row['title'] ?? null),
                    'subject' => $this->toNullableString($row['subject'] ?? null),
                    'meta' => is_array($row['meta'] ?? null) ? $row['meta'] : [],
                ];
            })
            ->filter(fn (array $result): bool => $result['text'] !== '')
            ->take(1)
            ->values()
            ->all();
    }

    private function buildOutput(array $results): string
    {
        return collect($results)
            ->map(fn (array $result): string => trim((string) ($result['text'] ?? '')))
            ->filter()
            ->implode("\n\n");
    }

    private function buildResponseFromAssistant(
        Message $assistantMessage,
        Conversation $conversation,
        int $userId
    ): array {
        $metadata = is_array($assistantMessage->metadata ?? null) ? $assistantMessage->metadata : [];
        $results = $this->normalizeResults($metadata['results'] ?? []);
        $type = (string) ($metadata['type'] ?? ($assistantMessage->is_error ? 'error' : 'result'));
        $wallet = Wallet::where('user_id', $userId)->first();

        return [
            'success' => $type !== 'error',
            'type' => $type,
            'tool' => self::TOOL_KEY,
            'provider' => $this->toNullableString($metadata['provider'] ?? null) ?? 'openrouter',
            'model_key' => self::MODEL_KEY,
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'message' => $this->toNullableString($metadata['message'] ?? null)
                ?? trim((string) $assistantMessage->content),
            'state' => $this->normalizeState($metadata['state'] ?? []),
            'results' => $results,
            'count' => (int) ($metadata['count'] ?? count($results)),
            'request_id' => $this->toNullableString($metadata['request_id'] ?? null),
            'debug' => null,
            'usage' => is_array($metadata['usage'] ?? null)
                ? $this->normalizeUsage($metadata['usage'])
                : null,
            'cost' => is_array($metadata['cost'] ?? null)
                ? $this->normalizeCost($metadata['cost'])
                : null,
            'tokens_deducted' => (int) ($metadata['tokens_deducted'] ?? 0),
            'wallet' => [
                'balance' => $wallet ? (int) $wallet->balance : null,
                'payback_balance' => $wallet ? (int) ($wallet->payback_balance ?? 0) : null,
            ],
            'assistant_message_id' => $assistantMessage->id,
        ];
    }

    private function normalizeUsage(mixed $usage): array
    {
        $usage = is_array($usage) ? $usage : [];
        $input = is_numeric($usage['input_tokens'] ?? null) ? (int) $usage['input_tokens'] : 0;
        $output = is_numeric($usage['output_tokens'] ?? null) ? (int) $usage['output_tokens'] : 0;

        return [
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => is_numeric($usage['total_tokens'] ?? null) ? (int) $usage['total_tokens'] : 0,
        ];
    }

    private function normalizeCost(mixed $cost): array
    {
        $cost = is_array($cost) ? $cost : [];
        $input = is_numeric($cost['input_cost'] ?? null) ? (float) $cost['input_cost'] : 0.0;
        $output = is_numeric($cost['output_cost'] ?? null) ? (float) $cost['output_cost'] : 0.0;
        $search = is_numeric($cost['web_search_cost'] ?? null)
            ? (float) $cost['web_search_cost']
            : 0.0;

        return [
            'input_cost' => $input,
            'output_cost' => $output,
            'web_search_cost' => $search,
            'total_cost' => is_numeric($cost['total_cost'] ?? null)
                ? (float) $cost['total_cost']
                : $input + $output + $search,
            'currency' => strtoupper($this->toNullableString($cost['currency'] ?? 'USD') ?? 'USD'),
        ];
    }

    private function deductWalletTokens(User $user, int $tokens): array
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

        $balance = (int) $wallet->balance;
        $payback = (int) ($wallet->payback_balance ?? 0);

        if ($tokens > 0) {
            if ($balance >= $tokens) {
                $wallet->balance = $balance - $tokens;
            } else {
                $wallet->balance = 0;
                $wallet->payback_balance = $payback + ($tokens - $balance);
            }
            $wallet->save();
            $wallet->refresh();
        }

        return [
            'balance' => (int) $wallet->balance,
            'payback_balance' => (int) ($wallet->payback_balance ?? 0),
        ];
    }

    private function toNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
}

