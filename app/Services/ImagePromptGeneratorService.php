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

class ImagePromptGeneratorService
{
    public const SUB_TOOL_ID = 24;
    public const TOOL_KEY = 'image_prompt_generator';
    public const MODEL_KEY = 'image_prompt_generator';
    public const ENDPOINT = 'tasks/image-prompt-generator/chat';

    private const DEFAULT_STATE = [
        'content' => null,
        'language' => 'English',
        'style' => 'high-end editorial photography',
        'aspect_ratio' => '4:5',
        'camera' => 'medium-wide shot',
        'lighting' => 'cinematic side lighting',
        'negative_prompt' => 'text, watermark, blurry image, distorted anatomy',
        'text_policy' => 'No text',
        'face_policy' => 'No visible human faces',
        'results_count' => 1,
        'extra_options' => ['realistic materials', '8K detail'],
        'last_output' => null,
    ];

    public function __construct(
        private readonly AiArabicWriterService $writerService,
        private readonly ConversationMessageCacheService $messageCache
    ) {}

    public static function supports(int $subToolId, ?string $toolKey = null, ?string $modelKey = null): bool
    {
        return $subToolId === self::SUB_TOOL_ID
            || strtolower(trim((string) $toolKey)) === self::TOOL_KEY
            || strtolower(trim((string) $modelKey)) === self::MODEL_KEY;
    }

    public function handle(Conversation $conversation, array $data, string $content, int $userId): array
    {
        if ((int) $conversation->user_id !== $userId) {
            abort(403, 'Conversation does not belong to the authenticated user.');
        }

        if ((int) $conversation->sub_tool_id !== self::SUB_TOOL_ID) {
            abort(422, 'This conversation is not an image prompt generation conversation.');
        }

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? '')) ?: (string) Str::uuid();
        $lock = Cache::lock("image-prompt-generator:{$userId}:{$conversation->id}:{$idempotencyKey}", 120);

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

        $regenerate = (bool) ($data['regenerate'] ?? false);
        $state = $this->normalizeState(is_array($data['state'] ?? null) ? $data['state'] : [], $regenerate);
        $originalContent = $state['content'] ?: $this->stripPromptPrefix($content);
        $state['content'] = $originalContent !== '' ? $originalContent : null;

        $requestPayload = [
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => (string) $conversation->uuid,
            'user_message' => $content,
            'state' => $state,
            'tool' => self::TOOL_KEY,
            'tool_key' => self::TOOL_KEY,
            'task_key' => self::TOOL_KEY,
            'model_key' => self::MODEL_KEY,
            'regenerate' => $regenerate,
            'debug' => (bool) ($data['debug'] ?? false),
        ];

        if ($regenerate && is_string($state['last_output']) && trim($state['last_output']) !== '') {
            $requestPayload['previous_output'] = trim($state['last_output']);
        }

        $userMessage = $existingUserMessage ?: Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $originalContent !== '' ? $originalContent : $content,
            'idempotency_key' => $idempotencyKey,
            'is_error' => false,
            'metadata' => [
                'type' => 'image_prompt_generation_request',
                'tool' => self::TOOL_KEY,
                'tool_key' => self::TOOL_KEY,
                'task_key' => self::TOOL_KEY,
                'model_key' => self::MODEL_KEY,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'conversation_uuid' => (string) $conversation->uuid,
                'state' => $state,
                'request_payload' => $requestPayload,
                'request_prompt' => $originalContent !== '' ? $originalContent : $content,
                'provider_user_message' => $content,
                'regenerate' => $regenerate,
            ],
        ]);

        $userMessage->setRelation('conversation', $conversation);
        if ($userMessage->wasRecentlyCreated) {
            $this->messageCache->updateAfterMessage($userMessage);
            $this->clearCache($userId);
        }

        $conversation->loadMissing('user.wallet', 'subTool');
        $providerResponse = $this->writerService->generateReplyWithUsage(
            $requestPayload,
            $this->resolveEndpoint($conversation)
        );
        $providerResponse = is_array($providerResponse)
            ? $providerResponse
            : ['reply' => (string) $providerResponse];

        if ($this->providerResponseIsError($providerResponse)) {
            throw new AiServiceException($this->providerErrorMessage($providerResponse), [
                'tool' => self::TOOL_KEY,
                'model_key' => self::MODEL_KEY,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'provider_response' => $providerResponse,
            ]);
        }

        $reply = $this->extractReply($providerResponse);
        if ($reply === '') {
            throw new AiServiceException('The image prompt generator returned an empty response.', [
                'tool' => self::TOOL_KEY,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'provider_response' => $providerResponse,
            ]);
        }

        $providerState = $this->extractState($providerResponse);
        $responseState = $this->normalizeState(array_replace($state, $providerState), false);
        $responseState['content'] = $responseState['content'] ?: ($originalContent !== '' ? $originalContent : null);
        $responseState['last_output'] = $this->toNullableString($responseState['last_output'] ?? null) ?: $reply;

        $usage = $this->normalizeUsage($providerResponse['usage'] ?? data_get($providerResponse, 'raw.usage', []));
        $cost = $this->normalizeCost($providerResponse['cost'] ?? data_get($providerResponse, 'raw.cost', []));
        $requestId = $this->toNullableString($providerResponse['request_id'] ?? data_get($providerResponse, 'raw.request_id'));
        $provider = $this->toNullableString($providerResponse['provider'] ?? data_get($providerResponse, 'raw.provider')) ?? 'openrouter';
        $modelKey = $this->toNullableString($providerResponse['model_key'] ?? data_get($providerResponse, 'raw.model_key')) ?? self::MODEL_KEY;
        $tokensToDeduct = (int) ($usage['total_tokens'] ?? 0);
        $assistantMessage = null;

        DB::transaction(function () use (
            $conversation,
            $userMessage,
            $userId,
            $reply,
            $provider,
            $modelKey,
            $requestId,
            $usage,
            $cost,
            $tokensToDeduct,
            $requestPayload,
            $responseState,
            &$assistantMessage
        ): void {
            if ($tokensToDeduct > 0 && $conversation->user) {
                $this->deductWalletTokens($conversation->user, $tokensToDeduct);
            }

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $reply,
                'is_error' => false,
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'success' => true,
                    'type' => 'result',
                    'tool' => self::TOOL_KEY,
                    'tool_key' => self::TOOL_KEY,
                    'task_key' => self::TOOL_KEY,
                    'model_key' => $modelKey,
                    'provider' => $provider,
                    'request_id' => $requestId,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'conversation_uuid' => (string) $conversation->uuid,
                    'reply' => $reply,
                    'message' => $reply,
                    'state' => $responseState,
                    'request_payload' => $requestPayload,
                    'request_prompt' => $responseState['content'],
                    'usage' => $usage,
                    'cost' => $cost,
                    'tokens_deducted' => $tokensToDeduct,
                ],
            ]);

            if ($tokensToDeduct > 0 || (float) ($cost['total_cost'] ?? 0) > 0) {
                CostLogger::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'sub_tool_id' => self::SUB_TOOL_ID,
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

        return $this->responseFromAssistant($assistantMessage, $conversation, $userId);
    }

    private function normalizeState(array $state, bool $regenerate): array
    {
        $merged = array_replace(self::DEFAULT_STATE, $state);
        $extraOptions = $merged['extra_options'] ?? [];

        if (! is_array($extraOptions)) {
            $extraOptions = preg_split('/[\n,]+/', (string) $extraOptions) ?: [];
        }

        $merged['extra_options'] = array_values(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $extraOptions
        )));
        $merged['results_count'] = max(1, min(5, (int) ($merged['results_count'] ?? 1)));

        foreach (['content', 'language', 'style', 'aspect_ratio', 'camera', 'lighting', 'negative_prompt', 'text_policy', 'face_policy'] as $key) {
            $merged[$key] = $this->toNullableString($merged[$key] ?? null);
        }

        $merged['language'] ??= self::DEFAULT_STATE['language'];
        $merged['style'] ??= self::DEFAULT_STATE['style'];
        $merged['aspect_ratio'] ??= self::DEFAULT_STATE['aspect_ratio'];
        $merged['camera'] ??= self::DEFAULT_STATE['camera'];
        $merged['lighting'] ??= self::DEFAULT_STATE['lighting'];
        $merged['negative_prompt'] ??= self::DEFAULT_STATE['negative_prompt'];
        $merged['text_policy'] ??= self::DEFAULT_STATE['text_policy'];
        $merged['face_policy'] ??= self::DEFAULT_STATE['face_policy'];
        $merged['last_output'] = $regenerate ? $this->toNullableString($merged['last_output'] ?? null) : $this->toNullableString($merged['last_output'] ?? null);

        return $merged;
    }

    private function responseFromAssistant(Message $assistantMessage, Conversation $conversation, int $userId): array
    {
        $metadata = is_array($assistantMessage->metadata ?? null) ? $assistantMessage->metadata : [];
        $state = is_array($metadata['state'] ?? null) ? $this->normalizeState($metadata['state'], false) : self::DEFAULT_STATE;
        $reply = (string) ($metadata['reply'] ?? $metadata['message'] ?? $assistantMessage->content);

        return [
            'success' => (bool) ($metadata['success'] ?? ! $assistantMessage->is_error),
            'type' => (string) ($metadata['type'] ?? ($assistantMessage->is_error ? 'error' : 'result')),
            'reply' => $reply,
            'message' => $reply,
            'task_key' => self::TOOL_KEY,
            'tool' => self::TOOL_KEY,
            'tool_key' => self::TOOL_KEY,
            'model_key' => (string) ($metadata['model_key'] ?? self::MODEL_KEY),
            'provider' => (string) ($metadata['provider'] ?? 'openrouter'),
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => (string) $conversation->uuid,
            'request_id' => $metadata['request_id'] ?? null,
            'debug' => null,
            'usage' => $this->normalizeUsage($metadata['usage'] ?? []),
            'cost' => $this->normalizeCost($metadata['cost'] ?? []),
            'state' => $state,
            'assistant_message_id' => $assistantMessage->id,
            'wallet' => $this->walletSnapshot($userId),
        ];
    }

    private function resolveEndpoint(Conversation $conversation): string
    {
        $conversation->loadMissing('subTool');
        $config = is_array($conversation->subTool?->config ?? null) ? $conversation->subTool->config : [];

        return trim((string) ($config['endpoint'] ?? ''))
            ?: (trim((string) ($conversation->subTool?->endpoint ?? '')) ?: self::ENDPOINT);
    }

    private function extractReply(array $response): string
    {
        foreach ([$response, $response['raw'] ?? null, data_get($response, 'raw.data')] as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            foreach (['reply', 'message', 'content'] as $key) {
                $value = $this->toNullableString($payload[$key] ?? null);
                if ($value !== null) {
                    return $value;
                }
            }

            $lastOutput = $this->toNullableString(data_get($payload, 'state.last_output'));
            if ($lastOutput !== null) {
                return $lastOutput;
            }

            $results = $payload['results'] ?? null;
            if (is_array($results)) {
                $text = collect($results)
                    ->map(fn (mixed $item): ?string => is_array($item) ? $this->toNullableString($item['text'] ?? null) : $this->toNullableString($item))
                    ->filter()
                    ->implode("\n\n");

                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    private function extractState(array $response): array
    {
        foreach ([$response['state'] ?? null, data_get($response, 'raw.state'), data_get($response, 'raw.data.state')] as $state) {
            if (is_array($state)) {
                return $state;
            }
        }

        return [];
    }

    private function providerResponseIsError(array $response): bool
    {
        $success = $response['success'] ?? data_get($response, 'raw.success', data_get($response, 'raw.data.success', true));
        $type = strtolower(trim((string) ($response['type'] ?? data_get($response, 'raw.type', data_get($response, 'raw.data.type', '')))));

        return $success === false || $type === 'error';
    }

    private function providerErrorMessage(array $response): string
    {
        return $this->toNullableString($response['message'] ?? null)
            ?? $this->toNullableString($response['reply'] ?? null)
            ?? $this->toNullableString(data_get($response, 'raw.message'))
            ?? $this->toNullableString(data_get($response, 'raw.error'))
            ?? 'Image prompt generation failed.';
    }

    private function stripPromptPrefix(string $content): string
    {
        return trim(preg_replace('/^Create a professional image prompt for\s+/iu', '', trim($content)) ?? $content);
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
        } catch (\Throwable $th) {
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

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
