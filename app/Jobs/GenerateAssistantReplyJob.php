<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\AI\AIPayloadBuilder;
use App\Services\AiArabicWriterService;
use App\Services\ConversationMessageCacheService;
use App\Services\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAssistantReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 90;

    public function __construct(public int $userMessageId)
    {
    }

    public function handle(
        AiArabicWriterService $writerService,
        AIPayloadBuilder $payloadBuilder,
        ConversationMessageCacheService $messageCache,
        QdrantService $qdrantService
    ): void {
        $userMessage = Message::with([
            'conversation.user',
            'conversation.subTool',
        ])->find($this->userMessageId);

        if (! $userMessage || ! $userMessage->conversation) {
            return;
        }

        $conversation = $userMessage->conversation;
        $payload = $payloadBuilder->build($conversation, $userMessage);
        $payload = $payloadBuilder->withContext(
            $payload,
            $this->qdrantContext($userMessage, $qdrantService)
        );

        $this->storeMessageInQdrant($userMessage, $qdrantService);

        try {
            $content = $writerService->generateReply($payload);
            $isError = false;
        } catch (\Throwable $th) {
            Log::warning('Assistant generation failed; saving fallback reply.', [
                'conversation_id' => $conversation->id,
                'message_id' => $userMessage->id,
                'error' => $th->getMessage(),
            ]);

            $content = 'Sorry, I could not generate a response right now. Please try again.';
            $isError = true;
        }

        $assistantMessage = Message::create([
            'conversation_id' => $conversation->id,
            'content' => $content,
            'role' => 'assistant',
            'is_error' => $isError,
        ]);

        $assistantMessage->setRelation('conversation', $conversation);
        $messageCache->updateAfterMessage($assistantMessage);
        $this->storeMessageInQdrant($assistantMessage, $qdrantService);
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
}
