<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Repository\Messages\MessageInterface;
use App\Services\ConversationMessageCacheService;
use App\Services\QdrantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    use ApiResponse;

    private $message;
    private ConversationMessageCacheService $messageCache;
    private QdrantService $qdrantService;

    public function __construct(
        MessageInterface $message,
        ConversationMessageCacheService $messageCache,
        QdrantService $qdrantService
    )
    {
        $this->message = $message;
        $this->messageCache = $messageCache;
        $this->qdrantService = $qdrantService;
    }

    public function sendMessage(MessageRequest $request)
    {
        try {
            $data = $request->validated();
            $send = $this->message->send($data);

            if ($send && isset($send->id)) {
                $send->loadMissing('conversation');
                $this->messageCache->updateAfterMessage($send);
                $this->storeMessageInQdrant($send);
            }

            $this->clearCache(auth()->user()->id);
            return $this->success($send, 'Message Sent Successfully.');
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
}
