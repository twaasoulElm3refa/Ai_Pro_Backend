<?php

namespace App\Repository\Messages;

use App\Models\Message;
use Illuminate\Support\Facades\Log;

class MessageRepository implements MessageInterface
{
    public function send($data)
    {
        try {
            $conversationId = (int) ($data['conversation_id'] ?? 0);
            $role = (string) ($data['role'] ?? 'user');
            $content = trim((string) ($data['content'] ?? ''));
            $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));

            if ($conversationId <= 0 || $content === '') {
                return null;
            }

            if ($role === 'user' && $idempotencyKey !== '') {
                return Message::firstOrCreate(
                    [
                        'conversation_id' => $conversationId,
                        'role' => 'user',
                        'idempotency_key' => $idempotencyKey,
                    ],
                    [
                        'content' => $content,
                        'is_error' => false,
                        'reply_to_message_id' => null,
                    ]
                );
            }

            return Message::create($data);
        } catch (\Throwable $th) {
            Log::error('Message repository send failed.', [
                'error' => $th->getMessage(),
                'data' => [
                    'conversation_id' => $data['conversation_id'] ?? null,
                    'role' => $data['role'] ?? null,
                    'has_idempotency_key' => ! empty($data['idempotency_key'] ?? null),
                    'content_length' => isset($data['content']) ? mb_strlen((string) $data['content']) : null,
                ],
            ]);

            return null;
        }
    }
}
