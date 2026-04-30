<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ConversationMessageCacheService
{
    public function key(string $uuid): string
    {
        return "conversation_{$uuid}_messages";
    }

    public function get(string $uuid): ?array
    {
        $cached = Cache::get($this->key($uuid));

        if (! is_string($cached)) {
            return null;
        }

        $messages = json_decode($cached, true);

        return is_array($messages) ? $messages : null;
    }

    public function remember(Conversation $conversation): array
    {
        $messages = $conversation->message()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $cachedMessages = $this->toAiMessages($messages);

        Cache::put(
            $this->key($conversation->uuid),
            json_encode($cachedMessages, JSON_UNESCAPED_UNICODE),
            now()->addMinutes(30)
        );

        return $cachedMessages;
    }

    public function updateAfterMessage(Message $message): void
    {
        $conversation = $message->conversation;

        if (! $conversation) {
            return;
        }

        $messages = $this->get($conversation->uuid);

        if ($messages === null) {
            $this->remember($conversation);

            return;
        }

        $messages[] = $this->toAiMessage($message);

        Cache::put(
            $this->key($conversation->uuid),
            json_encode($messages, JSON_UNESCAPED_UNICODE),
            now()->addMinutes(30)
        );
    }

    public function forget(string $uuid): void
    {
        Cache::forget($this->key($uuid));
    }

    public function toResponseMessages(array $messages): array
    {
        return array_map(static function (array $message) {
            return [
                'role' => $message['role'] ?? 'user',
                'content' => $message['content'] ?? '',
            ];
        }, $messages);
    }

    protected function toAiMessages(Collection $messages): array
    {
        return $messages
            ->map(fn (Message $message) => $this->toAiMessage($message))
            ->values()
            ->all();
    }

    protected function toAiMessage(Message $message): array
    {
        return [
            'role' => $message->role,
            'content' => $message->content,
        ];
    }
}
