<?php

namespace App\Jobs;

use App\Exceptions\AiServiceException;
use App\Models\CostLogger;
use App\Models\Message;
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
use Illuminate\Support\Facades\Log;

class GenerateAssistantReplyJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;
    public int $uniqueFor = 300;

    public function __construct(public int $userMessageId, public ?array $taskOptions = null)
    {
    }

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
        Log::info('GenerateAssistantReplyJob task options', [
            'user_message_id' => $this->userMessageId,
            'task_options' => $this->taskOptions,
        ]);

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

            /*
             * User message stats
             */
            $userWordsCount = $this->countWords($userMessage->content);
            $userLanguage = $this->detectLanguage($userMessage->content);
            $userTokenMultiplier = $this->tokenMultiplierByLanguage($userLanguage);
            $inputTokens = (int) ceil($userWordsCount * $userTokenMultiplier);

            Log::info('User message words/language calculated inside assistant job', [
                'conversation_id' => $conversation->id,
                'user_message_id' => $userMessage->id,
                'user_language' => $userLanguage,
                'user_words_count' => $userWordsCount,
                'user_token_multiplier' => $userTokenMultiplier,
                'input_tokens_estimated' => $inputTokens,
            ]);

            $payload = $payloadBuilder->build($conversation, $userMessage);
            $payload = $payloadBuilder->withTaskOptions($payload, $this->taskOptions);

            /*
             * Inject last 6 messages from Qdrant collection as context.
             * This uses latestMessagesPayloads() from QdrantService,
             * not vector search.
             */
            if ((bool) config('services.aiarabic.inject_qdrant_context', false)) {
                $context = $this->qdrantContext($userMessage, $qdrantService);

                if ($context !== '') {
                    $payload = $payloadBuilder->withContext($payload, $context);
                }
            }

            Log::info('AI model payload prepared', [
                'conversation_id' => $conversation->id,
                'user_message_id' => $userMessage->id,
                'user_language' => $userLanguage,
                'user_words_count' => $userWordsCount,
                'input_tokens_estimated' => $inputTokens,
                'payload' => $payload,
            ]);

            /*
             * Store the current user message in Qdrant.
             * ملاحظة: ده بعد تجهيز الـ payload، عشان آخر 6 رسائل ما يبقاش فيهم نفس رسالة المستخدم الحالية مرتين.
             */
            $this->storeMessageInQdrant($userMessage, $qdrantService);

            try {
                $response = method_exists($writerService, 'generateReplyWithUsage')
                    ? $writerService->generateReplyWithUsage($payload)
                    : $writerService->generateReply($payload);

                if (is_array($response)) {
                    $content = (string) ($response['reply'] ?? $response['content'] ?? '');
                    $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
                } else {
                    $content = (string) $response;
                    $usage = [];
                }

                $isError = false;

                /*
                 * AI response stats
                 */
                Log::info('AI usage received', [
                    'conversation_id' => $conversation->id,
                    'user_message_id' => $userMessage->id,
                    'usage' => $usage,
                ]);

                $estimatedOutputTokens = $this->estimateOutputTokens($content);
                $inputTokens = (int) ($usage['input_tokens'] ?? $inputTokens);
                $outputTokens = (int) ($usage['output_tokens'] ?? $estimatedOutputTokens);
                $totalTokens = (int) ($usage['total_tokens'] ?? ($inputTokens + $outputTokens));

                Log::info('Cost logger tokens resolved', [
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'total_tokens' => $totalTokens,
                    'source' => ! empty($usage) ? 'provider_usage' : 'estimated',
                ]);

            } catch (AiServiceException $th) {
                Log::error('Assistant generation failed with AI service exception.', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $userMessage->id,
                    'payload' => $payload,
                    'user_language' => $userLanguage,
                    'user_words_count' => $userWordsCount,
                    'input_tokens_estimated' => $inputTokens,
                    'error_message' => $th->getMessage(),
                    'error_file' => $th->getFile(),
                    'error_line' => $th->getLine(),
                    'error_trace' => $th->getTraceAsString(),
                    'ai_context' => $th->context(),
                ]);

                throw $th;
            } catch (\Throwable $th) {
                Log::error('Assistant generation failed with unexpected exception.', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $userMessage->id,
                    'payload' => $payload,
                    'user_language' => $userLanguage,
                    'user_words_count' => $userWordsCount,
                    'input_tokens_estimated' => $inputTokens,
                    'error_message' => $th->getMessage(),
                    'error_file' => $th->getFile(),
                    'error_line' => $th->getLine(),
                    'error_trace' => $th->getTraceAsString(),
                ]);

                throw $th;
            }

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
             * تسجيل التكلفة التقريبية
             */
            CostLogger::create([
                'conversation_id' => $conversation->id,
                'user_id' => $conversation->user_id,
                'sub_tool_id' => $conversation->sub_tool_id ?? 1,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'input_cost' => ($inputTokens / 1000000) * 1.25,
                'output_cost' => ($outputTokens / 1000000) * 10,
                'total_cost' => (($inputTokens / 1000000) * 1.25) + (($outputTokens / 1000000) * 10),
            ]);

            $this->clearProfileCache($conversation->user_id);
        } finally {
            Cache::forget(self::dispatchMarkerKey($this->userMessageId));
            $lock->release();
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

        /*
         * يحسب كلمات العربي والإنجليزي والروسي والفرنسي والأرقام
         * الصيني لا يحتوي دائمًا على مسافات، لذلك لو النص صيني يتم حساب الأحرف الصينية كوحدات تقريبية.
         */
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

        /*
         * نحسب عدد الحروف من كل Script
         */
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

        /*
         * الفرنسي والإنجليزي الاتنين Latin.
         * بنميز الفرنسي من الحروف المميزة.
         * لو مفيش حروف فرنسية واضحة، هنعتبره English.
         */
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
            /*
             * متوسطات تقريبية وليست Tokenizer حقيقي
             */
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
