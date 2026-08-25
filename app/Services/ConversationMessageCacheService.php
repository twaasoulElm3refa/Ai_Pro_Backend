<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ConversationMessageCacheService
{
    protected const ERROR_PATTERNS = [
        '/sorry,\s*i could not generate a response/i',
        '/could not generate a response/i',
        '/please try again/i',
        '/assistant response timed out/i',
        '/connection interrupted/i',
        '/something went wrong/i',
    ];

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

        if (! is_array($messages)) {
            return null;
        }

        return collect($messages)
            ->filter(fn (array $message) => $this->isValidCachedMessage($message))
            ->values()
            ->all();
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

        $cleanMessage = $this->toAiMessage($message);

        if (! $cleanMessage) {
            return;
        }

        $messages = $this->get($conversation->uuid);

        if ($messages === null) {
            $this->remember($conversation);

            return;
        }

        $messages[] = $cleanMessage;

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
        return collect($messages)
            ->filter(fn (array $message) => $this->isValidCachedMessage($message))
            ->map(static function (array $message) {
                $metadata = is_array($message['metadata'] ?? null)
                    ? $message['metadata']
                    : null;

                $responseMessage = [
                    'id' => $message['id'] ?? null,
                    'role' => $message['role'] ?? 'user',
                    'content' => $message['content'] ?? '',
                    'created_at' => $message['created_at'] ?? null,
                    'is_error' => (bool) ($message['is_error'] ?? false),
                    'metadata' => $metadata,
                    'sub_tool_id' => $message['sub_tool_id'] ?? null,
                ];

                if (($responseMessage['role'] ?? null) !== 'assistant' || $metadata === null) {
                    return $responseMessage;
                }

                foreach ([
                    'success',
                    'type',
                    'tool',
                    'provider',
                    'model_key',
                    'request_id',
                    'user_id',
                    'sub_tool_id',
                    'conversation_uuid',
                    'state',
                    'results',
                    'normalized_results',
                    'file',
                    'images',
                    'generation',
                    'failed_files',
                    'count',
                    'usage',
                    'cost',
                    'debug',
                    'summary',
                    'video_id',
                    'transcript_language',
                    'transcript_chars',
                    'transcript_segments',
                    'transcript_is_generated',
                    'transcript',
                    'detected_language',
                    'duration_seconds',
                    'original_filename',
                ] as $key) {
                    if (array_key_exists($key, $metadata)) {
                        $responseMessage[$key] = $metadata[$key];
                    }
                }

                return $responseMessage;
            })
            ->values()
            ->all();
    }

    protected function toAiMessages(Collection $messages): array
    {
        return $messages
            ->map(fn (Message $message) => $this->toAiMessage($message))
            ->filter()
            ->values()
            ->all();
    }

    protected function toAiMessage(Message $message): ?array
    {
        $role = (string) $message->role;
        $content = trim((string) $message->content);

        if (! in_array($role, ['user', 'assistant'], true)) {
            return null;
        }

        if ($content === '') {
            return null;
        }

        $isError = (bool) ($message->is_error ?? false);

        if ($role === 'assistant' && ! $isError && $this->looksLikeFallbackError($content)) {
            return null;
        }

        return [
            'id' => $message->id,
            'role' => $role,
            'content' => $content,
            'created_at' => optional($message->created_at)->toISOString(),
            'is_error' => $isError,
            'metadata' => is_array($message->metadata ?? null) ? $message->metadata : null,
            'sub_tool_id' => $message->relationLoaded('conversation')
                ? ($message->conversation?->sub_tool_id ?? null)
                : null,
        ];
    }

    protected function isValidCachedMessage(array $message): bool
    {
        $role = (string) ($message['role'] ?? '');
        $content = trim((string) ($message['content'] ?? ''));

        if (! in_array($role, ['user', 'assistant'], true)) {
            return false;
        }

        if ($content === '') {
            return false;
        }

        $isError = (bool) ($message['is_error'] ?? false);

        if ($role === 'assistant' && ! $isError && $this->looksLikeFallbackError($content)) {
            return false;
        }

        return true;
    }

    protected function looksLikeFallbackError(string $content): bool
    {
        foreach (self::ERROR_PATTERNS as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return true;
            }
        }

        return false;
    }
}
