<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Jobs\GenerateAssistantReplyJob;
use App\Models\Message;
use App\Repository\Messages\MessageInterface;
use App\Services\ConversationMessageCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            $userId = (int) auth()->id();
            $lockKey = $this->requestLockKey($userId, (int) $data['conversation_id'], (string) $data['idempotency_key']);
            $lock = Cache::lock($lockKey, 10);
            $processed = $lock->block(3, function () use ($data) {
                return DB::transaction(function () use ($data) {
                    $existing = Message::where('conversation_id', $data['conversation_id'])
                        ->where('role', 'user')
                        ->where('idempotency_key', $data['idempotency_key'])
                        ->first();

                    if ($existing) {
                        return [$existing, false];
                    }

                    $userMessage = $this->message->send($data);

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
                $this->dispatchAssistantReplyIfNeeded($userMessage);
            }

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

    protected function dispatchAssistantReplyIfNeeded(Message $userMessage): void
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

        GenerateAssistantReplyJob::dispatch($userMessage->id)->afterResponse();
    }

    protected function requestLockKey(int $userId, int $conversationId, string $idempotencyKey): string
    {
        return "message-send:{$userId}:{$conversationId}:{$idempotencyKey}";
    }
}
