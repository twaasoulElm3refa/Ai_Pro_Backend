<?php

namespace App\Jobs;

use App\Exceptions\AiServiceException;
use App\Models\CostLogger;
use App\Models\Message;
use App\Models\Wallet;
use App\Services\AI\AIPayloadBuilder;
use App\Services\AiArabicWriterService;
use App\Services\ConversationMessageCacheService;
use App\Services\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateAssistantReplyJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public function __construct(public int $userMessageId, public ?array $taskOptions = null) {}

    public function uniqueId(): string
    {
        return "assistant-reply:{$this->userMessageId}";
    }

    public static function dispatchMarkerKey(int $userMessageId): string
    {
        return "assistant-reply-dispatched:{$userMessageId}";
    }

    public function handle(
        AiArabicWriterService $writerService,
        AIPayloadBuilder $payloadBuilder,
        ConversationMessageCacheService $messageCache,
        QdrantService $qdrantService
    ): void {


        $lock = Cache::lock("assistant-reply-lock:{$this->userMessageId}", 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $userMessage = Message::with([
                'conversation.user',
                'conversation.subTool',
            ])->find($this->userMessageId);

            if (! $userMessage || ! $userMessage->conversation) {
                return;
            }

            $existingAssistant = Message::where('role', 'assistant')
                ->where('reply_to_message_id', $this->userMessageId)
                ->first();

            if ($existingAssistant) {
                return;
            }

            $conversation = $userMessage->conversation;
            $endpoint = trim((string) ($conversation->subTool?->endpoint ?? ''));
            $insufficientPointsMessage = 'Insufficient points. Please recharge your wallet to continue.';

            if ($endpoint === '') {

                throw new AiServiceException('Sub tool endpoint is missing.', [
                    'conversation_id' => $conversation->id,
                    'sub_tool_id' => $conversation->sub_tool_id,
                    'user_message_id' => $userMessage->id,
                ]);
            }

            /*
             |--------------------------------------------------------------------------
             | 1) Before AI Call: settle old payback first
             |--------------------------------------------------------------------------
             */
            $canCallProvider = false;
            $walletBalanceBeforeProvider = 0;
            $paybackBalanceBeforeProvider = 0;

            DB::transaction(function () use (
                $conversation,
                &$canCallProvider,
                &$walletBalanceBeforeProvider,
                &$paybackBalanceBeforeProvider
            ): void {
                $wallet = Wallet::query()
                    ->where('user_id', $conversation->user_id)
                    ->lockForUpdate()
                    ->first();

                if (! $wallet) {
                    $canCallProvider = false;
                    $walletBalanceBeforeProvider = 0;
                    $paybackBalanceBeforeProvider = 0;

                    return;
                }

                $balance = (int) $wallet->balance;
                $paybackBalance = (int) ($wallet->payback_balance ?? 0);

                if ($paybackBalance > 0 && $balance > 0) {
                    $paybackPaid = min($balance, $paybackBalance);

                    $wallet->balance = $balance - $paybackPaid;
                    $wallet->payback_balance = $paybackBalance - $paybackPaid;
                    $wallet->save();
                }

                $walletBalanceBeforeProvider = (int) $wallet->balance;
                $paybackBalanceBeforeProvider = (int) ($wallet->payback_balance ?? 0);

                $canCallProvider = $walletBalanceBeforeProvider > 0;
            });

            if (! $canCallProvider) {
                Log::warning('Wallet balance is 0 before provider call', [
                    'conversation_id' => $conversation->id,
                ]);
                $this->createAssistantErrorMessage(
                    $userMessage,
                    $insufficientPointsMessage,
                    $messageCache
                );

                $this->clearProfileCache($conversation->user_id);

                return;
            }

            $userWordsCount = $this->countWords($userMessage->content);
            $userLanguage = $this->detectLanguage($userMessage->content);
            $userTokenMultiplier = $this->tokenMultiplierByLanguage($userLanguage);
            $inputTokens = (int) ceil($userWordsCount * $userTokenMultiplier);

            $payload = $payloadBuilder->build($conversation, $userMessage);
            $payload = $payloadBuilder->withTaskOptions($payload, $this->taskOptions);

            if ((bool) config('services.aiarabic.inject_qdrant_context', false)) {
                $context = $this->qdrantContext($userMessage, $qdrantService);

                if ($context !== '') {
                    $payload = $payloadBuilder->withContext($payload, $context);
                }
            }

            $this->storeMessageInQdrant($userMessage, $qdrantService);

            $providerCost = [];
            $providerRequestId = null;
            $modelKey = null;
            $providerHasTotalCost = false;

            try {
                $response = method_exists($writerService, 'generateReplyWithUsage')
                    ? $writerService->generateReplyWithUsage($payload, $endpoint)
                    : $writerService->generateReply($payload, $endpoint);

                if (is_array($response)) {
                    $content = (string) ($response['reply'] ?? $response['content'] ?? '');
                    $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
                    $providerCost = is_array($response['cost'] ?? null) ? $response['cost'] : [];
                    $providerRequestId = isset($response['request_id']) ? (string) $response['request_id'] : null;
                    $modelKey = isset($response['model_key']) ? (string) $response['model_key'] : null;
                } else {
                    $content = (string) $response;
                    $usage = [];
                }

                $isError = false;

                /*
                 |--------------------------------------------------------------------------
                 | Normalize Provider Tokens
                 |--------------------------------------------------------------------------
                 | المهم هنا:
                 | لو الـ API رجّع input_tokens/output_tokens/total_tokens، نخزنهم كما هم.
                 | التقدير يستخدم فقط لو الـ provider مرجعش القيمة.
                 */
                $estimatedOutputTokens = $this->estimateOutputTokens($content);

                $providerInputTokens = $usage['input_tokens']
                    ?? $usage['input_token']
                    ?? $usage['prompt_tokens']
                    ?? null;

                $providerOutputTokens = $usage['output_tokens']
                    ?? $usage['output_token']
                    ?? $usage['completion_tokens']
                    ?? null;

                $providerTotalTokens = $usage['total_tokens']
                    ?? $usage['total_token']
                    ?? null;

                $inputTokens = is_numeric($providerInputTokens)
                    ? (int) $providerInputTokens
                    : (int) $inputTokens;

                $outputTokens = is_numeric($providerOutputTokens)
                    ? (int) $providerOutputTokens
                    : (int) $estimatedOutputTokens;

                $totalTokens = is_numeric($providerTotalTokens)
                    ? (int) $providerTotalTokens
                    : ((int) $inputTokens + (int) $outputTokens);


                /*
                 |--------------------------------------------------------------------------
                 | Normalize Provider Cost
                 |--------------------------------------------------------------------------
                 */
                $inputCost = (float) ($providerCost['input_cost'] ?? (($inputTokens / 1000000) * 1.25));
                $outputCost = (float) ($providerCost['output_cost'] ?? (($outputTokens / 1000000) * 10));
                $webSearchCost = (float) ($providerCost['web_search_cost'] ?? 0);
                $totalCost = (float) ($providerCost['total_cost'] ?? ($inputCost + $outputCost + $webSearchCost));
                $currency = (string) ($providerCost['currency'] ?? 'USD');
                $providerHasTotalCost = isset($providerCost['total_cost']) && is_numeric($providerCost['total_cost']);

            } catch (AiServiceException $th) {
                throw $th;
            } catch (\Throwable $th) {
                throw $th;
            }

            /*
             |--------------------------------------------------------------------------
             | 2) Convert final provider cost to points
             |--------------------------------------------------------------------------
             | هنا بنحسب النقاط من totalCost النهائي.
             | كده web_search_cost داخل في الحساب لو موجود.
             */
            $totalPoints = max((int) $totalTokens, 0);

            /*
             |--------------------------------------------------------------------------
             | 3) After AI Call: never block the answer
             |--------------------------------------------------------------------------
             */
            $walletBalance = null;
            $paybackBalance = null;
            $pointsCharged = 0;
            $pointsAddedToPayback = 0;

            DB::transaction(function () use (
                $conversation,
                $totalPoints,
                &$walletBalance,
                &$paybackBalance,
                &$pointsCharged,
                &$pointsAddedToPayback
            ): void {
                $wallet = Wallet::query()
                    ->where('user_id', $conversation->user_id)
                    ->lockForUpdate()
                    ->first();

                if (! $wallet) {
                    Log::warning('Wallet not found after provider call while charging conversation cost', [
                        'conversation_id' => $conversation->id,
                        'user_id' => $conversation->user_id,
                        'points_required' => $totalPoints,
                    ]);

                    $walletBalance = null;
                    $paybackBalance = null;
                    $pointsCharged = 0;
                    $pointsAddedToPayback = $totalPoints;

                    return;
                }

                $currentBalance = (int) $wallet->balance;
                $currentPaybackBalance = (int) ($wallet->payback_balance ?? 0);

                $pointsCharged = min($currentBalance, $totalPoints);
                $pointsAddedToPayback = max($totalPoints - $pointsCharged, 0);

                $wallet->balance = $currentBalance - $pointsCharged;
                $wallet->payback_balance = $currentPaybackBalance + $pointsAddedToPayback;
                $wallet->save();

                $walletBalance = (int) $wallet->balance;
                $paybackBalance = (int) $wallet->payback_balance;

                Log::info('Wallet charged after provider call with payback support', [
                    'conversation_id' => $conversation->id,
                    'user_id' => $conversation->user_id,
                    'points_required' => $totalPoints,
                    'points_charged' => $pointsCharged,
                    'points_added_to_payback' => $pointsAddedToPayback,
                    'wallet_balance' => $walletBalance,
                    'payback_balance' => $paybackBalance,
                ]);
            });

            /*
             |--------------------------------------------------------------------------
             | 4) Create assistant message
             |--------------------------------------------------------------------------
             */
            $assistantMessage = Message::firstOrCreate(
                [
                    'reply_to_message_id' => $userMessage->id,
                ],
                [
                    'conversation_id' => $conversation->id,
                    'content' => $content,
                    'role' => 'assistant',
                    'is_error' => $isError,
                ]
            );

            if ($assistantMessage->wasRecentlyCreated) {
                $assistantMessage->setRelation('conversation', $conversation);
                $messageCache->updateAfterMessage($assistantMessage);
                $this->storeMessageInQdrant($assistantMessage, $qdrantService);
            }

            /*
             |--------------------------------------------------------------------------
             | 5) Save cost logger
             |--------------------------------------------------------------------------
             */
            $cost = CostLogger::create([
                'conversation_id' => $conversation->id,
                'user_id' => $conversation->user_id,
                'sub_tool_id' => $conversation->sub_tool_id ?? 1,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'input_cost' => $inputCost,
                'output_cost' => $outputCost,
                'web_search_cost' => $webSearchCost,
                'total_cost' => $totalCost,
                'currency' => $currency,
                'provider_request_id' => $providerRequestId,
                'model_key' => $modelKey,
            ]);

            $this->clearProfileCache($conversation->user_id);
        } finally {
            Cache::forget(self::dispatchMarkerKey($this->userMessageId));
            $lock->release();
        }
    }

    protected function createAssistantErrorMessage(
        Message $userMessage,
        string $content,
        ConversationMessageCacheService $messageCache
    ): void {
        $conversation = $userMessage->conversation;

        if (! $conversation) {
            return;
        }

        $assistantMessage = Message::firstOrCreate(
            [
                'reply_to_message_id' => $userMessage->id,
            ],
            [
                'conversation_id' => $conversation->id,
                'content' => $content,
                'role' => 'assistant',
                'is_error' => true,
            ]
        );

        if ($assistantMessage->wasRecentlyCreated) {
            $assistantMessage->setRelation('conversation', $conversation);
            $messageCache->updateAfterMessage($assistantMessage);
        }
    }

    protected function qdrantContext(Message $userMessage, QdrantService $qdrantService): string
    {
        $messages = $qdrantService->latestMessagesPayloads(
            $qdrantService->collectionName((int) $userMessage->conversation_id),
            6,
            true
        );

        return collect($messages)
            ->pluck('content')
            ->filter()
            ->unique()
            ->values()
            ->implode("\n");
    }

    protected function storeMessageInQdrant(Message $message, QdrantService $qdrantService): void
    {
        $conversation = $message->conversation;

        if (! $conversation || (bool) ($message->is_error ?? false)) {
            return;
        }

        $qdrantService->insertMessage(
            $qdrantService->collectionName((int) $message->conversation_id),
            [
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'content' => $message->content,
                'role' => $message->role,
                'sender_type' => $message->role,
                'user_id' => $conversation->user_id,
                'created_at' => optional($message->created_at)->toISOString() ?? now()->toISOString(),
            ]
        );
    }

    protected function countWords(?string $text): int
    {
        $text = trim((string) $text);

        if ($text === '') {
            return 0;
        }

        $language = $this->detectLanguage($text);

        if ($language === 'chinese') {
            preg_match_all('/\p{Han}/u', $text, $matches);

            return count($matches[0] ?? []);
        }

        preg_match_all('/[\p{Arabic}\p{L}\p{N}]+/u', $text, $matches);

        return count($matches[0] ?? []);
    }

    protected function detectLanguage(?string $text): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return 'unknown';
        }

        $patterns = [
            'arabic' => '/\p{Arabic}/u',
            'chinese' => '/\p{Han}/u',
            'russian' => '/\p{Cyrillic}/u',
            'latin' => '/\p{Latin}/u',
        ];

        $scores = [];

        foreach ($patterns as $language => $pattern) {
            preg_match_all($pattern, $text, $matches);
            $scores[$language] = count($matches[0] ?? []);
        }

        arsort($scores);

        $topLanguage = array_key_first($scores);
        $topScore = $scores[$topLanguage] ?? 0;

        if ($topScore <= 0) {
            return 'unknown';
        }

        if ($topLanguage === 'latin') {
            if (preg_match('/[àâçéèêëîïôûùüÿñæœ]/iu', $text)) {
                return 'french';
            }

            return 'english';
        }

        return $topLanguage;
    }

    protected function tokenMultiplierByLanguage(string $language): float
    {
        return match ($language) {
            'arabic' => 2.2,
            'chinese' => 2.5,
            'russian' => 1.5,
            'french' => 1.3,
            'english' => 1.2,
            default => 2.0,
        };
    }

    protected function estimateOutputTokens(string $content): int
    {
        $aiWordsCount = $this->countWords($content);
        $aiLanguage = $this->detectLanguage($content);
        $aiTokenMultiplier = $this->tokenMultiplierByLanguage($aiLanguage);

        return (int) ceil($aiWordsCount * $aiTokenMultiplier);
    }

    public function clearProfileCache($userId = null): void
    {
        $userId = $userId ?? auth()->id();

        if (! $userId) {
            return;
        }

        Cache::forget("user_profile_{$userId}");
    }
}
