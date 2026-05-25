<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class AIPayloadBuilder
{
    protected const VALID_ROLES = ['user', 'assistant'];

    protected const ERROR_PATTERNS = [
        '/sorry,\s*i could not generate a response/i',
        '/could not generate a response/i',
        '/please try again/i',
        '/assistant response timed out/i',
        '/something went wrong/i',
    ];

    public function build(Conversation $conversation, Message $latestUserMessage): array
    {
        $conversation->loadMissing(['user', 'subTool']);

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Message $message) => $this->toCleanMessage($message))
            ->filter()
            ->values()
            ->all();

        $payload = [
            'user_id' => $conversation->user_id,
            'sub_tool_id' => $conversation->sub_tool_id,
            'title' => $this->titleFromMessages($messages, $conversation),
            'conversation_uuid' => $conversation->uuid,
            'body' => $this->bodyFromMessages($messages),
            'user_message' => trim((string) $latestUserMessage->content),
        ];

        $this->validate($payload);

        Log::debug('AI Arabic writer payload prepared.', [
            'user_id' => $payload['user_id'],
            'sub_tool_id' => $payload['sub_tool_id'],
            'conversation_uuid' => $payload['conversation_uuid'],
            'body_length' => mb_strlen($payload['body']),
            'latest_message_id' => $latestUserMessage->id,
            'streaming_ready' => true,
        ]);

        return $payload;
    }

    public function withContext(array $payload, string $context): array
    {
        $context = $this->cleanContext($context);

        if ($context === '') {
            return $payload;
        }

        $payload['body'] = "Relevant prior context:\n{$context}\n\n" . $payload['body'];

        return $payload;
    }

    public function withTaskOptions(array $payload, ?array $taskOptions): array
    {
        if (! is_array($taskOptions)) {
            $taskOptions = [];
        }

        $searchMode = (string) ($taskOptions['search_mode'] ?? 'off');
        if (! in_array($searchMode, ['on', 'off'], true)) {
            $searchMode = 'off';
        }

        $normalized = [
            'search_mode' => $searchMode,
            'max_tokens' => isset($taskOptions['max_tokens'])
                ? (int) $taskOptions['max_tokens']
                : 4000,
            'temperature' => isset($taskOptions['temperature'])
                ? (float) $taskOptions['temperature']
                : 0.45,
        ];

        if ($searchMode === 'on') {
            $normalized['web_search_max_results'] = isset($taskOptions['web_search_max_results'])
                ? (int) $taskOptions['web_search_max_results']
                : 3;
            $normalized['web_search_total_results'] = isset($taskOptions['web_search_total_results'])
                ? (int) $taskOptions['web_search_total_results']
                : 5;
        }

        $payload['task_options'] = $normalized;

        return $payload;
    }

    public function withState(array $payload, ?array $state): array
    {
        if (! is_array($state) || empty($state)) {
            return $payload;
        }

        $payload['state'] = $state;

        return $payload;
    }

    public function cleanContext(string $context): string
    {
        return collect(preg_split('/\R/u', $context) ?: [])
            ->map(static fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '' && ! $this->looksLikeFallbackError($line))
            ->unique()
            ->values()
            ->implode("\n");
    }

    protected function toCleanMessage(Message $message): ?array
    {
        $role = (string) $message->role;
        $content = trim((string) $message->content);

        if (! in_array($role, self::VALID_ROLES, true) || $content === '') {
            return null;
        }

        if ((bool) ($message->is_error ?? false)) {
            return null;
        }

        if ($role === 'assistant' && $this->looksLikeFallbackError($content)) {
            return null;
        }

        return [
            'role' => $role,
            'content' => $content,
        ];
    }

    protected function titleFromMessages(array $messages, Conversation $conversation): string
    {
        foreach ($messages as $message) {
            if (($message['role'] ?? null) === 'user' && ! empty($message['content'])) {
                return mb_substr($message['content'], 0, 120);
            }
        }

        return 'Conversation ' . $conversation->uuid;
    }

    protected function bodyFromMessages(array $messages): string
    {
        return collect($messages)
            ->map(function (array $message) {
                $role = $message['role'] ?? 'user';
                $content = trim((string) ($message['content'] ?? ''));

                return "{$role}: {$content}";
            })
            ->filter(static fn (string $line) => trim($line) !== '')
            ->implode("\n\n");
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

    protected function validate(array $payload): void
    {
        if (empty($payload['user_id'])) {
            throw new InvalidArgumentException('AI payload is missing user_id.');
        }

        if (empty($payload['sub_tool_id'])) {
            throw new InvalidArgumentException('AI payload is missing sub_tool_id.');
        }

        if (empty($payload['title'])) {
            throw new InvalidArgumentException('AI payload is missing title.');
        }

        if (empty($payload['conversation_uuid'])) {
            throw new InvalidArgumentException('AI payload is missing conversation_uuid.');
        }

        if (empty($payload['body'])) {
            throw new InvalidArgumentException('AI payload is missing body.');
        }

        if (empty($payload['user_message'])) {
            throw new InvalidArgumentException('AI payload is missing user_message.');
        }
    }
}
