<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Jobs\GenerateAssistantReplyJob;
use App\Models\Conversation;
use App\Models\CostLogger;
use App\Models\Message;
use App\Models\User;
use App\Models\Wallet;
use App\Repository\Messages\MessageInterface;
use App\Services\AiArabicWriterService;
use App\Services\ConversationMessageCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MessageController extends Controller
{
    use ApiResponse;

    private const HEADLINE_GENERATOR_SUB_TOOL_ID = 4;
    private const HEADLINE_ENDPOINT = 'tasks/headline-generator/chat';

    private MessageInterface $message;

    private ConversationMessageCacheService $messageCache;

    public function __construct(
        MessageInterface $message,
        ConversationMessageCacheService $messageCache
    ) {
        $this->message = $message;
        $this->messageCache = $messageCache;
    }

    public function sendMessage(MessageRequest $request, AiArabicWriterService $writerService)
    {
        try {
            $data = $request->validated();
            $userId = (int) auth()->id();

            if ($userId <= 0) {
                return $this->unauthorized('Unauthorized.');
            }

            $conversation = $this->resolveConversation($data, $userId);
            if (! $conversation) {
                return $this->validationError([
                    'conversation_id' => ['Conversation not found.'],
                    'conversation_uuid' => ['Conversation not found.'],
                ], 'Invalid conversation.');
            }

            $subToolId = (int) ($data['sub_tool_id'] ?? $conversation->sub_tool_id ?? 0);
            $content = $this->resolveInputContent($data);

            if ($content === '') {
                return $this->validationError([
                    'user_message' => ['Message content is required.'],
                ], 'Invalid message data.');
            }

            if ($subToolId === self::HEADLINE_GENERATOR_SUB_TOOL_ID) {
                $request->validate([
                    'sub_tool_id' => ['required', 'integer'],
                    'conversation_uuid' => ['nullable', 'uuid'],
                    'user_message' => ['nullable', 'string', 'max:5000'],
                    'message' => ['nullable', 'string', 'max:5000'],
                    'content' => ['nullable', 'string', 'max:5000'],
                    'debug' => ['nullable', 'boolean'],
                    'state' => ['nullable', 'array'],
                    'state.content' => ['nullable', 'string', 'max:1000'],
                    'state.content_type' => ['nullable', 'string', 'max:100'],
                    'state.goal' => ['nullable', 'string', 'max:100'],
                    'state.language' => ['nullable', 'string', 'max:50'],
                    'state.tone' => ['nullable', 'string', 'max:50'],
                    'state.number_of_headlines' => ['nullable', 'integer', 'min:1', 'max:20'],
                    'state.headline_length' => ['nullable', 'string', 'max:50'],
                    'state.extra_options' => ['nullable', 'array'],
                    'state.extra_options.*' => ['string', 'max:150'],
                ]);

                $messageText = $request->input('user_message')
                    ?? $request->input('message')
                    ?? $request->input('content');

                if (! is_string($messageText) || trim($messageText) === '') {
                    return $this->validationError([
                        'user_message' => ['Message text is required.'],
                    ], 'Invalid message data.');
                }

                return $this->handleHeadlineGeneratorFlow(
                    $writerService,
                    $conversation,
                    $data,
                    $content,
                    $userId
                );
            }

            return $this->handleDefaultFlow($conversation, $data, $content, $userId);
        } catch (Throwable $th) {
            Log::error('Send message failed.', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
                'request_input' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            if (app()->environment('local')) {
                return response()->json([
                    'success' => false,
                    'error' => $th->getMessage(),
                    'trace' => $this->shortTrace($th),
                ], 500);
            }

            return $this->error('Sorry, I could not generate a response right now.');
        }
    }

    protected function handleDefaultFlow(Conversation $conversation, array $data, string $content, int $userId)
    {
        $normalized = $data;
        $normalized['conversation_id'] = (int) $conversation->id;
        $normalized['role'] = 'user';
        $normalized['content'] = $content;
        $normalized['is_error'] = false;
        $normalized['idempotency_key'] = trim((string) ($normalized['idempotency_key'] ?? ''));

        if ($normalized['idempotency_key'] === '') {
            $normalized['idempotency_key'] = (string) Str::uuid();
        }

        $taskOptions = $this->normalizeTaskOptions($normalized['task_options'] ?? null);
        $userWordsCount = $this->countWords($normalized['content']);
        $aiWordsCount = null;

        unset(
            $normalized['id'],
            $normalized['created_at'],
            $normalized['updated_at'],
            $normalized['deleted_at'],
            $normalized['reply_to_message_id'],
            $normalized['task_options'],
            $normalized['state'],
            $normalized['debug'],
            $normalized['user_message'],
            $normalized['message'],
            $normalized['conversation_uuid']
        );

        $lockKey = $this->requestLockKey(
            $userId,
            $normalized['conversation_id'],
            $normalized['idempotency_key']
        );

        $lock = Cache::lock($lockKey, 15);

        $processed = $lock->block(5, function () use ($normalized) {
            return DB::transaction(function () use ($normalized) {
                $existingByKey = Message::where('conversation_id', $normalized['conversation_id'])
                    ->where('role', 'user')
                    ->where('idempotency_key', $normalized['idempotency_key'])
                    ->first();

                if ($existingByKey) {
                    return [$existingByKey, false];
                }

                $existingRecentDuplicate = Message::where('conversation_id', $normalized['conversation_id'])
                    ->where('role', 'user')
                    ->where('content', $normalized['content'])
                    ->where('created_at', '>=', now()->subSeconds(20))
                    ->orderByDesc('id')
                    ->first();

                if ($existingRecentDuplicate) {
                    if (empty($existingRecentDuplicate->idempotency_key)) {
                        $existingRecentDuplicate->idempotency_key = $normalized['idempotency_key'];
                        $existingRecentDuplicate->save();
                    }

                    return [$existingRecentDuplicate, false];
                }

                $userMessage = $this->message->send($normalized);

                if (! $userMessage || ! isset($userMessage->id)) {
                    return [null, false];
                }

                return [$userMessage, true];
            }, 3);
        });

        /** @var Message|null $userMessage */
        $userMessage = $processed[0] ?? null;
        $wasCreated = (bool) ($processed[1] ?? false);

        if (! $userMessage || ! isset($userMessage->id)) {
            return $this->error('Message could not be saved.');
        }

        if ($wasCreated) {
            $userMessage->loadMissing('conversation');
            $this->messageCache->updateAfterMessage($userMessage);
            $this->clearCache($userId);
            $this->dispatchAssistantReplyIfNeeded($userMessage, $taskOptions);
        }

        return $this->success([
            'message_id' => $userMessage->id,
            'conversation_id' => $userMessage->conversation_id,
            'message' => $userMessage,
            'assistant' => null,
            'usage' => null,
            'cost' => null,
            'wallet' => [
                'points_charged' => null,
                'balance' => null,
            ],
            'was_created' => $wasCreated,
            'user_words_count' => $userWordsCount,
            'ai_words_count' => $aiWordsCount,
        ], 'Message Sent Successfully.');
    }

    protected function handleHeadlineGeneratorFlow(
        AiArabicWriterService $writerService,
        Conversation $conversation,
        array $data,
        string $content,
        int $userId
    ) {
        $initialState = [
            'content' => null,
            'content_type' => null,
            'goal' => null,
            'language' => null,
            'tone' => null,
            'number_of_headlines' => null,
            'headline_length' => null,
            'extra_options' => [],
        ];

        $state = array_merge($initialState, is_array($data['state'] ?? null) ? $data['state'] : []);
        $state = $this->normalizeHeadlineState($state);
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        $lockKey = $this->requestLockKey($userId, (int) $conversation->id, $idempotencyKey);
        $lock = Cache::lock($lockKey, 15);

        $processed = $lock->block(5, function () use ($conversation, $content, $idempotencyKey, $state) {
            return DB::transaction(function () use ($conversation, $content, $idempotencyKey, $state) {
                $existingByKey = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingByKey) {
                    return [$existingByKey, false];
                }

                $existingRecentDuplicate = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where('content', $content)
                    ->where('created_at', '>=', now()->subSeconds(20))
                    ->orderByDesc('id')
                    ->first();

                if ($existingRecentDuplicate) {
                    if (empty($existingRecentDuplicate->idempotency_key)) {
                        $existingRecentDuplicate->idempotency_key = $idempotencyKey;
                        $existingRecentDuplicate->save();
                    }

                    return [$existingRecentDuplicate, false];
                }

                $userMessage = $this->message->send([
                    'conversation_id' => (int) $conversation->id,
                    'role' => 'user',
                    'content' => $content,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => [
                        'type' => 'headline_request',
                        'tool' => 'ai_headline_generator',
                        'state' => $state,
                        'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
                        'conversation_uuid' => $conversation->uuid,
                    ],
                ]);

                if (! $userMessage || ! isset($userMessage->id)) {
                    return [null, false];
                }

                return [$userMessage, true];
            }, 3);
        });

        /** @var Message|null $userMessage */
        $userMessage = $processed[0] ?? null;
        $wasCreated = (bool) ($processed[1] ?? false);

        if (! $userMessage || ! isset($userMessage->id)) {
            return $this->error('Message could not be saved.');
        }

        $conversation->loadMissing('user.wallet');

        if (! $conversation->user) {
            return $this->error('Conversation user not found.');
        }

        $userMessage->loadMissing('conversation.subTool');
        $this->messageCache->updateAfterMessage($userMessage);
        $this->clearCache($userId);

        $existingAssistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('reply_to_message_id', $userMessage->id)
            ->first();

        if ($existingAssistant) {
            $cached = $this->buildHeadlineResponseFromAssistant($existingAssistant, $conversation, $userId);

            return $this->success($cached + ['was_created' => false], 'Headline Response Ready.');
        }

        $payload = [
            'user_id' => $userId,
            'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => $content,
            'debug' => (bool) ($data['debug'] ?? false),
            'state' => $state,
        ];

        if (! is_array($payload) || empty($payload) || trim((string) ($payload['user_message'] ?? '')) === '') {
            return $this->validationError([
                'payload' => ['Headline payload body is required.'],
            ], 'Invalid headline payload.');
        }

        Log::info('Headline generator payload prepared', [
            'conversation_id' => $conversation->id,
            'conversation_uuid' => $conversation->uuid,
            'payload_is_array' => is_array($payload),
            'payload_keys_count' => is_array($payload) ? count($payload) : 0,
            'state_keys_count' => is_array($payload['state'] ?? null) ? count($payload['state']) : 0,
        ]);

        $providerResponse = $writerService->generateReplyWithUsage($payload, self::HEADLINE_ENDPOINT);
        $raw = is_array($providerResponse['raw'] ?? null) ? $providerResponse['raw'] : [];

        $type = (string) (($providerResponse['type'] ?? null) ?? ($raw['type'] ?? 'message'));
        if (! in_array($type, ['question', 'result'], true)) {
            $type = 'message';
        }

        $responseState = $this->normalizeHeadlineState(
            is_array($providerResponse['state'] ?? null) ? $providerResponse['state'] : ($raw['state'] ?? [])
        );
        $mergedState = $this->mergeHeadlineState($state, $responseState);
        $headlines = $this->normalizeHeadlines(
            is_array($providerResponse['headlines'] ?? null) ? $providerResponse['headlines'] : ($raw['headlines'] ?? [])
        );

        $usage = $this->normalizeUsage($raw['usage'] ?? ($providerResponse['usage'] ?? []));
        $cost = $this->normalizeCost($raw['cost'] ?? ($providerResponse['cost'] ?? []));
        $tokensToDeduct = $this->getTokensToDeduct([
            'usage' => $usage,
        ]);

        $responseMessage = trim((string) ($raw['message'] ?? ($providerResponse['reply'] ?? '')));
        if ($responseMessage === '') {
            $responseMessage = $type === 'question'
                ? 'من فضلك أكمل البيانات المطلوبة.'
                : 'تم توليد العناوين بنجاح.';
        }

        $provider = $this->toNullableString(($providerResponse['provider'] ?? null) ?? ($raw['provider'] ?? null));
        $modelKey = $this->toNullableString(($providerResponse['model_key'] ?? null) ?? ($raw['model_key'] ?? null));
        $requestId = $this->toNullableString(($providerResponse['request_id'] ?? null) ?? ($raw['request_id'] ?? null));
        $tool = $this->toNullableString(($providerResponse['tool'] ?? null) ?? ($raw['tool'] ?? 'ai_headline_generator')) ?? 'ai_headline_generator';

        $assistantContent = $this->buildAssistantContent($type, $responseMessage, $headlines);
        $shouldResetState = $type === 'result';
        $walletSnapshot = [
            'balance' => null,
            'payback_balance' => null,
        ];
        $assistantMessage = null;

        DB::transaction(function () use (
            $conversation,
            $tokensToDeduct,
            $type,
            $usage,
            $cost,
            $assistantContent,
            $userMessage,
            $tool,
            $provider,
            $modelKey,
            $requestId,
            $mergedState,
            $headlines,
            $providerResponse,
            $raw,
            $responseMessage,
            $userId,
            &$walletSnapshot,
            &$assistantMessage
        ): void {
            $walletDetails = $this->deductWalletTokens(
                $conversation->user,
                $tokensToDeduct,
                'headline_generator_ai_usage'
            );

            $walletSnapshot = [
                'balance' => $walletDetails['wallet_after'] ?? null,
                'payback_balance' => $walletDetails['payback_after'] ?? null,
            ];

            Log::info('Headline generator wallet deduction', [
                'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
                'response_type' => $type,
                'tokens_to_deduct' => $tokensToDeduct,
                'wallet_before' => $walletDetails['wallet_before'] ?? null,
                'wallet_after' => $walletDetails['wallet_after'] ?? null,
                'payback_before' => $walletDetails['payback_before'] ?? null,
                'payback_after' => $walletDetails['payback_after'] ?? null,
                'user_id' => $conversation->user_id,
                'conversation_id' => $conversation->id,
            ]);

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $assistantContent,
                'is_error' => false,
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'type' => $type,
                    'tool' => $tool,
                    'provider' => $provider,
                    'model_key' => $modelKey,
                    'request_id' => $requestId,
                    'state' => $mergedState,
                    'headlines' => $headlines,
                    'message' => $responseMessage,
                    'count' => (int) (($providerResponse['count'] ?? null) ?? ($raw['count'] ?? count($headlines))),
                    'usage' => $usage,
                    'cost' => $cost,
                    'tokens_deducted' => $tokensToDeduct,
                    'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
                    'conversation_uuid' => $conversation->uuid,
                ],
            ]);

            CostLogger::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
                'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                'total_tokens' => (int) ($usage['total_tokens'] ?? (($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0))),
                'input_cost' => (float) ($cost['input_cost'] ?? 0),
                'output_cost' => (float) ($cost['output_cost'] ?? 0),
                'web_search_cost' => (float) ($cost['web_search_cost'] ?? 0),
                'total_cost' => (float) ($cost['total_cost'] ?? (($cost['input_cost'] ?? 0) + ($cost['output_cost'] ?? 0) + ($cost['web_search_cost'] ?? 0))),
                'currency' => (string) ($cost['currency'] ?? 'USD'),
                'provider_request_id' => $requestId,
                'model_key' => $modelKey,
            ]);
        });

        if (! $assistantMessage) {
            return $this->error('Assistant message could not be saved.');
        }

        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);

        $this->clearCache($userId);

        return $this->success([
            'success' => true,
            'type' => $type,
            'tool' => $tool,
            'provider' => $provider,
            'model_key' => $modelKey,
            'user_id' => $userId,
            'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'message' => $responseMessage,
            'state' => $mergedState,
            'headlines' => $headlines,
            'count' => (int) (($providerResponse['count'] ?? null) ?? ($raw['count'] ?? count($headlines))),
            'request_id' => $requestId,
            'debug' => null,
            'usage' => $usage,
            'cost' => $cost,
            'wallet' => [
                'balance' => $walletSnapshot['balance'],
                'payback_balance' => $walletSnapshot['payback_balance'],
            ],
            'should_reset_state' => $shouldResetState,
            'assistant_message_id' => $assistantMessage->id,
            'was_created' => $wasCreated,
        ], 'Headline Response Ready.');
    }

    protected function buildHeadlineResponseFromAssistant(Message $assistantMessage, Conversation $conversation, int $userId): array
    {
        $metadata = is_array($assistantMessage->metadata ?? null) ? $assistantMessage->metadata : [];
        $headlines = $this->normalizeHeadlines($metadata['headlines'] ?? []);
        $wallet = Wallet::where('user_id', $userId)->first();

        return [
            'success' => true,
            'type' => (string) ($metadata['type'] ?? 'message'),
            'tool' => (string) ($metadata['tool'] ?? 'ai_headline_generator'),
            'provider' => $this->toNullableString($metadata['provider'] ?? null),
            'model_key' => $this->toNullableString($metadata['model_key'] ?? null),
            'user_id' => $userId,
            'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'message' => $this->toNullableString($metadata['message'] ?? null) ?? trim((string) $assistantMessage->content),
            'state' => $this->normalizeHeadlineState($metadata['state'] ?? []),
            'headlines' => $headlines,
            'count' => (int) ($metadata['count'] ?? count($headlines)),
            'request_id' => $this->toNullableString($metadata['request_id'] ?? null),
            'debug' => null,
            'usage' => $this->normalizeUsage($metadata['usage'] ?? []),
            'cost' => $this->normalizeCost($metadata['cost'] ?? []),
            'wallet' => [
                'balance' => $wallet ? (int) $wallet->balance : null,
                'payback_balance' => $wallet ? (int) ($wallet->payback_balance ?? 0) : null,
            ],
            'should_reset_state' => (string) ($metadata['type'] ?? 'message') === 'result',
            'assistant_message_id' => $assistantMessage->id,
        ];
    }

    protected function buildAssistantContent(string $type, string $message, array $headlines): string
    {
        $intro = trim($message);

        if ($type !== 'result' || count($headlines) === 0) {
            return $intro;
        }

        $rows = collect($headlines)
            ->map(function (array $headline, int $index): string {
                $number = isset($headline['id']) && is_numeric($headline['id'])
                    ? (int) $headline['id']
                    : ($index + 1);

                $title = trim((string) ($headline['text'] ?? ''));
                $subheadline = trim((string) ($headline['subheadline'] ?? ''));

                if ($subheadline !== '') {
                    return "{$number}. {$title}\n   {$subheadline}";
                }

                return "{$number}. {$title}";
            })
            ->values()
            ->all();

        return trim($intro."\n\n".implode("\n", $rows));
    }

    protected function resolveConversation(array $data, int $userId): ?Conversation
    {
        $conversationId = isset($data['conversation_id']) ? (int) $data['conversation_id'] : 0;
        $conversationUuid = trim((string) ($data['conversation_uuid'] ?? ''));

        $query = Conversation::query()->where('user_id', $userId);

        if ($conversationId > 0) {
            return (clone $query)->where('id', $conversationId)->first();
        }

        if ($conversationUuid !== '') {
            return (clone $query)->where('uuid', $conversationUuid)->first();
        }

        return null;
    }

    protected function resolveInputContent(array $data): string
    {
        return trim((string) (
            $data['user_message']
            ?? $data['message']
            ?? $data['content']
            ?? ''
        ));
    }

    protected function normalizeHeadlineState(mixed $state): array
    {
        $state = is_array($state) ? $state : [];

        $base = [
            'content' => null,
            'content_type' => null,
            'goal' => null,
            'language' => null,
            'tone' => null,
            'number_of_headlines' => null,
            'headline_length' => null,
            'extra_options' => [],
        ];

        $merged = array_merge($base, $state);
        $merged['content'] = $this->toNullableString($merged['content'] ?? null);
        $merged['content_type'] = $this->toNullableString($merged['content_type'] ?? null);
        $merged['goal'] = $this->toNullableString($merged['goal'] ?? null);
        $merged['language'] = $this->toNullableString($merged['language'] ?? null);
        $merged['tone'] = $this->toNullableString($merged['tone'] ?? null);
        $merged['headline_length'] = $this->toNullableString($merged['headline_length'] ?? null);

        $number = $merged['number_of_headlines'] ?? null;
        if (is_numeric($number)) {
            $merged['number_of_headlines'] = max(1, min(20, (int) $number));
        } else {
            $merged['number_of_headlines'] = null;
        }

        $extraOptions = $merged['extra_options'] ?? [];
        if (! is_array($extraOptions)) {
            $extraOptions = [];
        }

        $merged['extra_options'] = collect($extraOptions)
            ->map(fn ($item) => $this->toNullableString($item))
            ->filter()
            ->values()
            ->all();

        return $merged;
    }

    protected function mergeHeadlineState(array $oldState, array $newState): array
    {
        $merged = $this->normalizeHeadlineState($oldState);

        foreach ($newState as $key => $value) {
            if ($key === 'extra_options' && is_array($value)) {
                $merged[$key] = $value;
                continue;
            }

            if ($value !== null && $value !== '') {
                $merged[$key] = $value;
            }
        }

        return $this->normalizeHeadlineState($merged);
    }

    protected function normalizeHeadlines(mixed $headlines): array
    {
        if (! is_array($headlines)) {
            return [];
        }

        return collect($headlines)
            ->map(function ($headline, int $index): array {
                $row = is_array($headline) ? $headline : [];

                $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : ($index + 1);

                return [
                    'id' => $id,
                    'text' => trim((string) ($row['text'] ?? '')),
                    'subheadline' => $this->toNullableString($row['subheadline'] ?? null),
                ];
            })
            ->filter(fn (array $headline) => $headline['text'] !== '')
            ->values()
            ->all();
    }

    protected function normalizeUsage(mixed $usage): array
    {
        $usage = is_array($usage) ? $usage : [];

        return [
            'input_tokens' => is_numeric($usage['input_tokens'] ?? null) ? (int) $usage['input_tokens'] : 0,
            'output_tokens' => is_numeric($usage['output_tokens'] ?? null) ? (int) $usage['output_tokens'] : 0,
            'total_tokens' => is_numeric($usage['total_tokens'] ?? null)
                ? (int) $usage['total_tokens']
                : ((is_numeric($usage['input_tokens'] ?? null) ? (int) $usage['input_tokens'] : 0)
                    + (is_numeric($usage['output_tokens'] ?? null) ? (int) $usage['output_tokens'] : 0)),
        ];
    }

    private function getTokensToDeduct(array $aiResponse): int
    {
        $usage = is_array($aiResponse['usage'] ?? null) ? $aiResponse['usage'] : [];

        $total = (int) ($usage['total_tokens'] ?? 0);

        if ($total > 0) {
            return $total;
        }

        return (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['output_tokens'] ?? 0);
    }

    private function deductWalletTokens(User $user, int $tokens, ?string $reason = null): array
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

        $walletBefore = (int) $wallet->balance;
        $paybackBefore = (int) ($wallet->payback_balance ?? 0);

        if ($tokens <= 0) {
            Log::warning('No tokens to deduct from wallet.', [
                'user_id' => $user->id,
                'tokens' => $tokens,
                'reason' => $reason,
            ]);

            return [
                'wallet_before' => $walletBefore,
                'wallet_after' => $walletBefore,
                'payback_before' => $paybackBefore,
                'payback_after' => $paybackBefore,
                'tokens' => 0,
            ];
        }

        if ($walletBefore >= $tokens) {
            $wallet->balance = $walletBefore - $tokens;
        } else {
            $wallet->balance = 0;
            $wallet->payback_balance = $paybackBefore + ($tokens - $walletBefore);
        }

        $wallet->save();
        $wallet->refresh();

        return [
            'wallet_before' => $walletBefore,
            'wallet_after' => (int) $wallet->balance,
            'payback_before' => $paybackBefore,
            'payback_after' => (int) ($wallet->payback_balance ?? 0),
            'tokens' => $tokens,
        ];
    }

    protected function normalizeCost(mixed $cost): array
    {
        $cost = is_array($cost) ? $cost : [];

        $inputCost = $this->toNullableFloat($cost['input_cost'] ?? null) ?? 0.0;
        $outputCost = $this->toNullableFloat($cost['output_cost'] ?? null) ?? 0.0;
        $webSearchCost = $this->toNullableFloat($cost['web_search_cost'] ?? null) ?? 0.0;
        $totalCost = $this->toNullableFloat($cost['total_cost'] ?? null) ?? ($inputCost + $outputCost + $webSearchCost);

        return [
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
            'web_search_cost' => $webSearchCost,
            'total_cost' => $totalCost,
            'currency' => strtoupper($this->toNullableString($cost['currency'] ?? 'USD') ?? 'USD'),
        ];
    }

    protected function toNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    protected function toNullableFloat(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected function clearCache($userId): void
    {
        try {
            Cache::tags(['conversations', "user_{$userId}"])->flush();
        } catch (\Throwable $th) {
            Log::debug('Conversation tagged cache flush skipped.', [
                'error' => $th->getMessage(),
            ]);
        }
    }

    protected function dispatchAssistantReplyIfNeeded(Message $userMessage, ?array $taskOptions = null): void
    {
        $assistantExists = Message::where('role', 'assistant')
            ->where('reply_to_message_id', $userMessage->id)
            ->exists();

        if ($assistantExists) {
            return;
        }

        $dispatchMarker = GenerateAssistantReplyJob::dispatchMarkerKey($userMessage->id);

        if (! Cache::add($dispatchMarker, true, now()->addMinutes(5))) {
            return;
        }

        GenerateAssistantReplyJob::dispatch($userMessage->id, $taskOptions)->afterResponse();
    }

    protected function requestLockKey(int $userId, int $conversationId, string $idempotencyKey): string
    {
        return "message-send:{$userId}:{$conversationId}:{$idempotencyKey}";
    }

    protected function shortTrace(Throwable $th, int $limit = 5): array
    {
        $trace = collect($th->getTrace())
            ->take($limit)
            ->map(function (array $frame): array {
                return [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'function' => $frame['function'] ?? null,
                ];
            })
            ->values()
            ->all();

        array_unshift($trace, [
            'file' => $th->getFile(),
            'line' => $th->getLine(),
            'function' => 'throw',
        ]);

        return $trace;
    }

    protected function normalizeTaskOptions(mixed $taskOptions): ?array
    {
        if (! is_array($taskOptions)) {
            return [
                'search_mode' => 'off',
                'max_tokens' => 1000,
                'temperature' => 0.45,
            ];
        }

        $searchMode = (string) ($taskOptions['search_mode'] ?? 'off');

        if (! in_array($searchMode, ['on', 'off'], true)) {
            $searchMode = 'off';
        }

        $normalized = [
            'search_mode' => $searchMode,
            'max_tokens' => isset($taskOptions['max_tokens'])
                ? (int) $taskOptions['max_tokens']
                : 1000,
            'temperature' => isset($taskOptions['temperature'])
                ? (float) $taskOptions['temperature']
                : 0.45,
        ];

        if ($searchMode === 'on') {
            $normalized['web_search_max_results'] = isset($taskOptions['web_search_max_results'])
                ? (int) $taskOptions['web_search_max_results']
                : 3;

            $normalized['web_search_total_results'] = isset($taskOptions['web_search_total_results'])
                ? (int) $taskOptions['web_search_total_results']
                : 5;
        }

        return $normalized;
    }

    protected function countWords(?string $text): int
    {
        $text = trim((string) $text);

        if ($text === '') {
            return 0;
        }

        preg_match_all('/[\p{Arabic}\p{L}\p{N}]+/u', $text, $matches);

        return count($matches[0] ?? []);
    }
}
