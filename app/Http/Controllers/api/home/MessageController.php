<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Repository\Messages\MessageInterface;
use App\Services\AiArabicWriterService;
use App\Services\ConversationMessageCacheService;
use App\Services\QdrantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    use ApiResponse;

    private $message;
    private AiArabicWriterService $writerService;
    private ConversationMessageCacheService $messageCache;
    private QdrantService $qdrantService;

    public function __construct(
        MessageInterface $message,
        AiArabicWriterService $writerService,
        ConversationMessageCacheService $messageCache,
        QdrantService $qdrantService
    )
    {
        $this->message = $message;
        $this->writerService = $writerService;
        $this->messageCache = $messageCache;
        $this->qdrantService = $qdrantService;
    }

    public function sendMessage(MessageRequest $request)
    {
        try {
            $data = $request->validated();
            $userMessage = $this->message->send($data);

            if (! $userMessage || ! isset($userMessage->id)) {
                return $this->error('Message could not be saved.');
            }

            $userMessage->loadMissing('conversation');
            $this->messageCache->updateAfterMessage($userMessage);
            $this->storeMessageInQdrant($userMessage);

            $history = $this->messageCache->get($userMessage->conversation->uuid)
                ?? $this->messageCache->remember($userMessage->conversation);

            $assistantMessage = $this->createAssistantMessage($userMessage, $history);

            $this->clearCache(auth()->user()->id);

            return $this->success($assistantMessage, 'Message Sent Successfully.');
        } catch (\Throwable $th) {
            Log::error($th);

            return $this->error('Something went wrong.');
        }
    }

    protected function clearCache($userId)
    {
        try {
            Cache::tags(['conversations', "user_{$userId}"])->flush();
        } catch (\Throwable $th) {
            Log::debug('Conversation tagged cache flush skipped.', ['error' => $th->getMessage()]);
        }
    }

    protected function storeMessageInQdrant($message): void
    {
        $conversation = $message->conversation;

        if (! $conversation) {
            return;
        }

        $this->qdrantService->insertMessage(
            $this->qdrantService->collectionName((int) $message->conversation_id),
            [
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'content' => $message->content,
                'user_id' => $conversation->user_id,
                'created_at' => optional($message->created_at)->toISOString(),
            ]
        );
    }

    protected function createAssistantMessage($userMessage, array $history)
    {
        try {
            $content = $this->writerService->generateReply($history);
            $isError = false;
        } catch (\Throwable $th) {
            Log::warning('Assistant generation failed; saving fallback reply.', [
                'conversation_id' => $userMessage->conversation_id,
                'message_id' => $userMessage->id,
                'error' => $th->getMessage(),
            ]);

            $content = 'Sorry, I could not generate a response right now. Please try again.';
            $isError = true;
        }

        $assistantMessage = $this->message->send([
            'conversation_id' => $userMessage->conversation_id,
            'content' => $content,
            'role' => 'assistant',
            'is_error' => $isError,
        ]);

        if ($assistantMessage && isset($assistantMessage->id)) {
            $assistantMessage->loadMissing('conversation');
            $this->messageCache->updateAfterMessage($assistantMessage);
            $this->storeMessageInQdrant($assistantMessage);
        }

        return $assistantMessage;
    }
}
