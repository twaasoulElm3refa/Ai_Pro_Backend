<?php

namespace App\Jobs;

use App\Exceptions\AiServiceException;
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
    public int $timeout = 90;
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
             * حساب عدد كلمات رسالة اليوزر
             */
            $userWordsCount = $this->countWords($userMessage->content);

            Log::info('User message words count inside assistant job', [
                'conversation_id' => $conversation->id,
                'user_message_id' => $userMessage->id,
                'user_words_count' => $userWordsCount,
            ]);

            $payload = $payloadBuilder->build($conversation, $userMessage);
            $payload = $payloadBuilder->withTaskOptions($payload, $this->taskOptions);

            if ((bool) config('services.aiarabic.inject_qdrant_context', false)) {
                $payload = $payloadBuilder->withContext(
                    $payload,
                    $this->qdrantContext($userMessage, $qdrantService)
                );
            }

            Log::info('AI model payload prepared', [
                'conversation_id' => $conversation->id,
                'user_message_id' => $userMessage->id,
                'user_words_count' => $userWordsCount,
                'payload' => $payload,
            ]);

            $this->storeMessageInQdrant($userMessage, $qdrantService);

            try {
                $content = $writerService->generateReply($payload);
                $isError = false;

                /*
                 * حساب عدد كلمات رد الـ AI بعد رجوع الرد مباشرة
                 */
                $aiWordsCount = $this->countWords($content);

                Log::info('AI response words count calculated', [
                    'conversation_id' => $conversation->id,
                    'user_message_id' => $userMessage->id,
                    'user_words_count' => $userWordsCount,
                    'ai_words_count' => $aiWordsCount,
                ]);
            } catch (AiServiceException $th) {
                Log::error('Assistant generation failed with AI service exception.', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $userMessage->id,
                    'payload' => $payload,
                    'user_words_count' => $userWordsCount,
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
                    'user_words_count' => $userWordsCount,
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

            Log::info('Assistant message saved with words count', [
                'conversation_id' => $conversation->id,
                'user_message_id' => $userMessage->id,
                'assistant_message_id' => $assistantMessage->id,
                'user_words_count' => $userWordsCount,
                'ai_words_count' => $aiWordsCount ?? null,
                'was_recently_created' => $assistantMessage->wasRecentlyCreated,
            ]);

            if ($assistantMessage->wasRecentlyCreated) {
                $assistantMessage->setRelation('conversation', $conversation);
                $messageCache->updateAfterMessage($assistantMessage);
                $this->storeMessageInQdrant($assistantMessage, $qdrantService);
            }
        } finally {
            Cache::forget(self::dispatchMarkerKey($this->userMessageId));
            $lock->release();
        }
    }

    protected function qdrantContext(Message $userMessage, QdrantService $qdrantService): string
    {
        $matches = $qdrantService->searchMessages(
            $qdrantService->collectionName((int) $userMessage->conversation_id),
            $userMessage->content,
            5
        );

        $context = collect($matches)
            ->pluck('payload.content')
            ->filter()
            ->unique()
            ->values()
            ->implode("\n");

        return $context;
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
                'user_id' => $conversation->user_id,
                'created_at' => optional($message->created_at)->toISOString(),
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
         * يحسب كلمات العربي والإنجليزي والأرقام
         */
        preg_match_all('/[\p{Arabic}\p{L}\p{N}]+/u', $text, $matches);

        return count($matches[0] ?? []);
    }
}
