<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Jobs\GenerateAssistantReplyJob;
use App\Repository\Messages\MessageInterface;
use App\Services\ConversationMessageCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    use ApiResponse;

    private $message;
    private ConversationMessageCacheService $messageCache;

    public function __construct(
        MessageInterface $message,
        ConversationMessageCacheService $messageCache
    )
    {
        $this->message = $message;
        $this->messageCache = $messageCache;
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
            GenerateAssistantReplyJob::dispatch($userMessage->id)->afterResponse();

            $this->clearCache(auth()->user()->id);

            return $this->success([
                'message_id' => $userMessage->id,
                'conversation_id' => $userMessage->conversation_id,
                'message' => $userMessage,
            ], 'Message Sent Successfully.');
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
}
