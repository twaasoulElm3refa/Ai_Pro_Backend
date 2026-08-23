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

class YouTubeSummarizerService
{
    public const SUB_TOOL_ID = 25;
    public const TOOL_KEY = 'youtube_summarizer';
    public const MODEL_KEY = 'youtube_summarizer';
    public const ENDPOINT = 'tasks/youtube-summarizer/chat';

    private const DEFAULT_STATE = [
        'transcript_languages' => ['ar', 'en'],
        'summary_language' => 'Arabic',
        'summary_style' => 'structured summary with a headline and key points',
        'max_summary_words' => 1000,
        'extra_options' => [],
        'last_output' => null,
    ];

    public function __construct(
        private readonly AiArabicWriterService $writerService,
        private readonly ConversationMessageCacheService $messageCache
    ) {}

    /**
     * This tool is deliberately strict: a YouTube request must identify all
     * three values rather than allowing another tool's conversation to route here.
     */
    public static function supports(int $subToolId, ?string $toolKey = null, ?string $modelKey = null): bool
    {
        return $subToolId === self::SUB_TOOL_ID
            && strtolower(trim((string) $toolKey)) === self::TOOL_KEY
            && strtolower(trim((string) $modelKey)) === self::MODEL_KEY;
    }

    /**
     * @return array{video_id: string, normalized_url: string}|null
     */
    public static function normalizeYouTubeUrl(mixed $value): ?array
    {
        if (! is_scalar($value)) {
            return null;
        }

        $url = trim((string) $value);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $videoId = null;
        if ($host === 'youtu.be' || $host === 'www.youtu.be') {
            $videoId = explode('/', trim((string) ($parts['path'] ?? ''), '/'))[0] ?? null;
        } else {
            $host = preg_replace('/^(?:www|m)\./', '', $host) ?: $host;
            if (! in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
                return null;
            }

            $segments = explode('/', trim((string) ($parts['path'] ?? ''), '/'));
            if (($segments[0] ?? '') === 'watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $videoId = $query['v'] ?? null;
            } elseif (in_array(($segments[0] ?? ''), ['shorts', 'embed', 'live'], true)) {
                $videoId = $segments[1] ?? null;
            }
        }

        $videoId = is_scalar($videoId) ? trim(rawurldecode((string) $videoId)) : '';
        if (preg_match('/^[A-Za-z0-9_-]{6,128}$/', $videoId) !== 1) {
            return null;
        }

        return [
            'video_id' => $videoId,
            'normalized_url' => 'https://www.youtube.com/watch?v='.$videoId,
        ];
    }

    public function handle(Conversation $conversation, array $data, string $content, int $userId): array
    {
        if ((int) $conversation->user_id !== $userId) {
            abort(403, 'Conversation does not belong to the authenticated user.');
        }

        if ((int) $conversation->sub_tool_id !== self::SUB_TOOL_ID) {
            abort(422, 'This conversation is not a YouTube summarizer conversation.');
        }

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? '')) ?: (string) Str::uuid();
        $lock = Cache::lock("youtube-summarizer:{$userId}:{$conversation->id}:{$idempotencyKey}", 300);

        return $lock->block(15, fn (): array => $this->handleLocked(
            $conversation,
            $data,
            $content,
            $userId,
            $idempotencyKey
        ));
    }

    private function handleLocked(
        Conversation $conversation,
        array $data,
        string $content,
        int $userId,
        string $idempotencyKey
    ): array {
        $existingUserMessage = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingUserMessage) {
            $existingAssistant = Message::query()
                ->where('reply_to_message_id', $existingUserMessage->id)
                ->where('role', 'assistant')
                ->first();

            if ($existingAssistant) {
                return $this->responseFromAssistant($existingAssistant, $conversation, $userId);
            }
        }

        $video = self::normalizeYouTubeUrl($content);
        if ($video === null) {
            throw new AiServiceException('Please provide a valid YouTube video URL.');
        }

        $regenerate = (bool) ($data['regenerate'] ?? false);
        $state = $this->normalizeState(is_array($data['state'] ?? null) ? $data['state'] : [], $regenerate);
        $requestPayload = [
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => (string) $conversation->uuid,
            'user_message' => $video['normalized_url'],
            'video_id' => $video['video_id'],
            'state' => $state,
            'tool' => self::TOOL_KEY,
            'tool_key' => self::TOOL_KEY,
            'task_key' => self::TOOL_KEY,
            'model_key' => self::MODEL_KEY,
            'regenerate' => $regenerate,
            'debug' => (bool) ($data['debug'] ?? false),
        ];

        if ($regenerate && $state['last_output'] !== null) {
            $requestPayload['previous_output'] = $state['last_output'];
        }

        $userMessage = $existingUserMessage ?: Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $video['normalized_url'],
            'idempotency_key' => $idempotencyKey,
            'is_error' => false,
            'metadata' => [
                'type' => 'youtube_summarizer_request',
                'tool' => self::TOOL_KEY,
                'tool_key' => self::TOOL_KEY,
                'task_key' => self::TOOL_KEY,
                'model_key' => self::MODEL_KEY,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'conversation_uuid' => (string) $conversation->uuid,
                'video_id' => $video['video_id'],
                'state' => $state,
                'request_payload' => $requestPayload,
                'request_prompt' => $video['normalized_url'],
                'regenerate' => $regenerate,
            ],
        ]);

        $userMessage->setRelation('conversation', $conversation);
        if ($userMessage->wasRecentlyCreated) {
            $this->messageCache->updateAfterMessage($userMessage);
            $this->clearCache($userId);
        }

        try {
            $conversation->loadMissing('user.wallet', 'subTool');
            $providerResponse = $this->writerService->generateReplyWithUsage(
                $requestPayload,
                $this->resolveEndpoint($conversation)
            );
            $providerResponse = is_array($providerResponse)
                ? $providerResponse
                : ['reply' => (string) $providerResponse];

            if ($this->providerResponseIsError($providerResponse)) {
                throw new AiServiceException($this->providerErrorMessage($providerResponse));
            }

            $summary = $this->extractSummary($providerResponse);
            if ($summary === '') {
                throw new AiServiceException('The YouTube summarizer returned an empty summary.');
            }
        } catch (AiServiceException $exception) {
            return $this->persistErrorResponse($conversation, $userMessage, $userId, $state, $video, $exception);
        }

        $providerState = $this->providerArray($providerResponse, 'state');
        $responseState = $this->normalizeState(array_replace($state, $providerState), false);
        $responseState['last_output'] = $summary;
        $metadata = [
            'video_id' => $this->providerString($providerResponse, 'video_id') ?? $video['video_id'],
            'transcript_language' => $this->providerString($providerResponse, 'transcript_language'),
            'transcript_chars' => $this->providerInt($providerResponse, 'transcript_chars'),
            'transcript_segments' => $this->providerInt($providerResponse, 'transcript_segments'),
            'transcript_is_generated' => $this->providerBool($providerResponse, 'transcript_is_generated'),
            'provider' => $this->providerString($providerResponse, 'provider') ?? 'supadata+openrouter',
            'model_key' => $this->providerString($providerResponse, 'model_key') ?? self::MODEL_KEY,
            'request_id' => $this->providerString($providerResponse, 'request_id'),
            'usage' => $this->normalizeUsage($providerResponse['usage'] ?? data_get($providerResponse, 'raw.usage', [])),
            'cost' => $this->normalizeCost($providerResponse['cost'] ?? data_get($providerResponse, 'raw.cost', [])),
        ];
        $tokensToDeduct = (int) ($metadata['usage']['total_tokens'] ?? 0);
        $assistantMessage = null;

        DB::transaction(function () use (
            $conversation,
            $userMessage,
            $userId,
            $summary,
            $state,
            $responseState,
            $requestPayload,
            $metadata,
            $tokensToDeduct,
            &$assistantMessage
        ): void {
            if ($tokensToDeduct > 0 && $conversation->user) {
                $this->deductWalletTokens($conversation->user, $tokensToDeduct);
            }

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $summary,
                'is_error' => false,
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'success' => true,
                    'type' => 'result',
                    'tool' => self::TOOL_KEY,
                    'tool_key' => self::TOOL_KEY,
                    'task_key' => self::TOOL_KEY,
                    'model_key' => $metadata['model_key'],
                    'provider' => $metadata['provider'],
                    'request_id' => $metadata['request_id'],
                    'user_id' => $userId,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'conversation_uuid' => (string) $conversation->uuid,
                    'summary' => $summary,
                    'message' => $summary,
                    'video_id' => $metadata['video_id'],
                    'transcript_language' => $metadata['transcript_language'],
                    'transcript_chars' => $metadata['transcript_chars'],
                    'transcript_segments' => $metadata['transcript_segments'],
                    'transcript_is_generated' => $metadata['transcript_is_generated'],
                    'summary_language' => $responseState['summary_language'],
                    'summary_style' => $responseState['summary_style'],
                    'max_summary_words' => $responseState['max_summary_words'],
                    'state' => $responseState,
                    'request_payload' => $requestPayload,
                    'request_prompt' => $requestPayload['user_message'],
                    'usage' => $metadata['usage'],
                    'cost' => $metadata['cost'],
                    'tokens_deducted' => $tokensToDeduct,
                ],
            ]);

            if ($tokensToDeduct > 0 || (float) ($metadata['cost']['total_cost'] ?? 0) > 0) {
                CostLogger::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'input_tokens' => (int) ($metadata['usage']['input_tokens'] ?? 0),
                    'output_tokens' => (int) ($metadata['usage']['output_tokens'] ?? 0),
                    'total_tokens' => $tokensToDeduct,
                    'input_cost' => (float) ($metadata['cost']['input_cost'] ?? 0),
                    'output_cost' => (float) ($metadata['cost']['output_cost'] ?? 0),
                    'web_search_cost' => (float) ($metadata['cost']['web_search_cost'] ?? 0),
                    'total_cost' => (float) ($metadata['cost']['total_cost'] ?? 0),
                    'currency' => (string) ($metadata['cost']['currency'] ?? 'USD'),
                    'provider_request_id' => $metadata['request_id'],
                    'model_key' => $metadata['model_key'],
                ]);
            }
        });

        if (! $assistantMessage) {
            throw new \RuntimeException('Assistant message could not be saved.');
        }

        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        return $this->responseFromAssistant($assistantMessage, $conversation, $userId);
    }

    private function persistErrorResponse(
        Conversation $conversation,
        Message $userMessage,
        int $userId,
        array $state,
        array $video,
        AiServiceException $exception
    ): array {
        $assistantMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Unable to summarize this YouTube video right now. Please try again.',
            'is_error' => true,
            'reply_to_message_id' => $userMessage->id,
            'metadata' => [
                'success' => false,
                'type' => 'error',
                'tool' => self::TOOL_KEY,
                'tool_key' => self::TOOL_KEY,
                'task_key' => self::TOOL_KEY,
                'model_key' => self::MODEL_KEY,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'conversation_uuid' => (string) $conversation->uuid,
                'message' => 'Unable to summarize this YouTube video right now. Please try again.',
                'video_id' => $video['video_id'],
                'state' => $state,
                'request_prompt' => $video['normalized_url'],
                'usage' => [],
                'cost' => [],
            ],
        ]);
        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        Log::warning('YouTube summarizer provider request failed.', [
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'video_id' => $video['video_id'],
            'error' => $exception->getMessage(),
        ]);

        return $this->responseFromAssistant($assistantMessage, $conversation, $userId);
    }

    private function responseFromAssistant(Message $assistantMessage, Conversation $conversation, int $userId): array
    {
        $metadata = is_array($assistantMessage->metadata ?? null) ? $assistantMessage->metadata : [];
        $state = is_array($metadata['state'] ?? null)
            ? $this->normalizeState($metadata['state'], false)
            : self::DEFAULT_STATE;
        $summary = (string) ($metadata['summary'] ?? $metadata['message'] ?? $assistantMessage->content);

        return [
            'success' => (bool) ($metadata['success'] ?? ! $assistantMessage->is_error),
            'type' => (string) ($metadata['type'] ?? ($assistantMessage->is_error ? 'error' : 'result')),
            'summary' => $summary,
            'message' => $summary,
            'task_key' => self::TOOL_KEY,
            'tool' => self::TOOL_KEY,
            'tool_key' => self::TOOL_KEY,
            'model_key' => (string) ($metadata['model_key'] ?? self::MODEL_KEY),
            'provider' => $metadata['provider'] ?? null,
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => (string) $conversation->uuid,
            'video_id' => $metadata['video_id'] ?? null,
            'transcript_language' => $metadata['transcript_language'] ?? null,
            'transcript_chars' => $metadata['transcript_chars'] ?? null,
            'transcript_segments' => $metadata['transcript_segments'] ?? null,
            'transcript_is_generated' => $metadata['transcript_is_generated'] ?? null,
            'request_id' => $metadata['request_id'] ?? null,
            'usage' => $this->normalizeUsage($metadata['usage'] ?? []),
            'cost' => $this->normalizeCost($metadata['cost'] ?? []),
            'state' => $state,
            'assistant_message_id' => $assistantMessage->id,
            'wallet' => $this->walletSnapshot($userId),
        ];
    }

    private function normalizeState(array $state, bool $regenerate): array
    {
        $merged = array_replace(self::DEFAULT_STATE, $state);
        $languages = is_array($merged['transcript_languages'] ?? null)
            ? $merged['transcript_languages']
            : self::DEFAULT_STATE['transcript_languages'];
        $languages = array_values(array_unique(array_filter(array_map(
            fn (mixed $language): string => strtolower(trim((string) $language)),
            $languages
        ), fn (string $language): bool => preg_match('/^[a-z]{2,3}(?:-[a-z]{2,4})?$/', $language) === 1)));

        $merged['transcript_languages'] = $languages ?: self::DEFAULT_STATE['transcript_languages'];
        $merged['summary_language'] = $this->toNullableString($merged['summary_language'] ?? null) ?? self::DEFAULT_STATE['summary_language'];
        $merged['summary_style'] = $this->toNullableString($merged['summary_style'] ?? null) ?? self::DEFAULT_STATE['summary_style'];
        $merged['max_summary_words'] = max(50, min(10000, (int) ($merged['max_summary_words'] ?? self::DEFAULT_STATE['max_summary_words'])));
        $merged['extra_options'] = $this->normalizeStringList($merged['extra_options'] ?? []);
        $merged['last_output'] = $regenerate
            ? $this->toNullableString($merged['last_output'] ?? null)
            : $this->toNullableString($merged['last_output'] ?? null);

        return $merged;
    }

    private function resolveEndpoint(Conversation $conversation): string
    {
        $conversation->loadMissing('subTool');
        $config = is_array($conversation->subTool?->config ?? null) ? $conversation->subTool->config : [];

        return trim((string) ($config['endpoint'] ?? ''))
            ?: (trim((string) ($conversation->subTool?->endpoint ?? '')) ?: self::ENDPOINT);
    }

    private function extractSummary(array $response): string
    {
        foreach (['summary', 'reply', 'message', 'content'] as $key) {
            $value = $this->providerString($response, $key);
            if ($value !== null) {
                return $value;
            }
        }

        return '';
    }

    private function providerResponseIsError(array $response): bool
    {
        $success = $response['success'] ?? data_get($response, 'raw.success', data_get($response, 'raw.data.success', true));
        $type = strtolower(trim((string) ($response['type'] ?? data_get($response, 'raw.type', data_get($response, 'raw.data.type', '')))));

        return $success === false || $type === 'error';
    }

    private function providerErrorMessage(array $response): string
    {
        return $this->providerString($response, 'message')
            ?? $this->providerString($response, 'error')
            ?? 'YouTube summarization failed.';
    }

    private function providerString(array $response, string $key): ?string
    {
        foreach ([$response, $response['raw'] ?? null, data_get($response, 'raw.data')] as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $value = $this->toNullableString($payload[$key] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function providerArray(array $response, string $key): array
    {
        foreach ([$response, $response['raw'] ?? null, data_get($response, 'raw.data')] as $payload) {
            if (is_array($payload) && is_array($payload[$key] ?? null)) {
                return $payload[$key];
            }
        }

        return [];
    }

    private function providerInt(array $response, string $key): ?int
    {
        foreach ([$response, $response['raw'] ?? null, data_get($response, 'raw.data')] as $payload) {
            if (is_array($payload) && is_numeric($payload[$key] ?? null)) {
                return (int) $payload[$key];
            }
        }

        return null;
    }

    private function providerBool(array $response, string $key): ?bool
    {
        foreach ([$response, $response['raw'] ?? null, data_get($response, 'raw.data')] as $payload) {
            if (is_array($payload) && array_key_exists($key, $payload)) {
                return filter_var($payload[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            }
        }

        return null;
    }

    private function normalizeUsage(mixed $usage): array
    {
        $usage = is_array($usage) ? $usage : [];

        return [
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
        ];
    }

    private function normalizeCost(mixed $cost): array
    {
        $cost = is_array($cost) ? $cost : [];
        $input = (float) ($cost['input_cost'] ?? 0);
        $output = (float) ($cost['output_cost'] ?? 0);
        $search = (float) ($cost['web_search_cost'] ?? 0);

        return [
            'input_cost' => $input,
            'output_cost' => $output,
            'web_search_cost' => $search,
            'total_cost' => (float) ($cost['total_cost'] ?? ($input + $output + $search)),
            'currency' => strtoupper(trim((string) ($cost['currency'] ?? 'USD')) ?: 'USD'),
        ];
    }

    private function normalizeStringList(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/[\n,]+/', (string) $value);

        return array_values(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $items ?: []
        )));
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
        } catch (\Throwable $exception) {
            Log::debug('Conversation tagged cache flush skipped.', ['error' => $exception->getMessage()]);
        }
    }

    private function toNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
