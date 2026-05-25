<?php

namespace App\Repository\Messages;

use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MessageRepository implements MessageInterface
{
    public function send($data)
    {
        try {
            $conversationId = (int) ($data['conversation_id'] ?? 0);
            $role = (string) ($data['role'] ?? 'user');
            $content = trim((string) ($data['content'] ?? ''));
            $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
            $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : null;

            if ($conversationId <= 0 || $content === '') {
                return null;
            }

            /*
             * مهم جدًا:
             * ممنوع أي user message تتخزن من غير idempotency_key
             */
            if ($role === 'user' && $idempotencyKey === '') {
                $idempotencyKey = (string) Str::uuid();
                $data['idempotency_key'] = $idempotencyKey;
            }

            if ($role === 'user') {
                /*
                 * لو نفس idempotency_key موجود، رجّع الموجود.
                 */
                $existingByKey = Message::where('conversation_id', $conversationId)
                    ->where('role', 'user')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingByKey) {
                    if ($metadata !== null && ! is_array($existingByKey->metadata ?? null)) {
                        $existingByKey->metadata = $metadata;
                        $existingByKey->save();
                    }

                    return $existingByKey;
                }

                /*
                 * لو نفس الرسالة اتخزنت قبلها بثواني، رجّع الموجودة بدل create جديد.
                 * ده يقفل مشكلة النسخة اللي داخلة NULL.
                 */
                $existingRecentDuplicate = Message::where('conversation_id', $conversationId)
                    ->where('role', 'user')
                    ->where('content', $content)
                    ->where('created_at', '>=', now()->subSeconds(30))
                    ->orderByDesc('id')
                    ->first();

                if ($existingRecentDuplicate) {
                    if (empty($existingRecentDuplicate->idempotency_key)) {
                        $existingRecentDuplicate->idempotency_key = $idempotencyKey;
                    }

                    if ($metadata !== null && ! is_array($existingRecentDuplicate->metadata ?? null)) {
                        $existingRecentDuplicate->metadata = $metadata;
                    }

                    if ($existingRecentDuplicate->isDirty(['idempotency_key', 'metadata'])) {
                        $existingRecentDuplicate->save();
                    }

                    return $existingRecentDuplicate;
                }

                return Message::create([
                    'conversation_id' => $conversationId,
                    'role' => 'user',
                    'content' => $content,
                    'idempotency_key' => $idempotencyKey,
                    'is_error' => false,
                    'reply_to_message_id' => null,
                    'metadata' => $metadata,
                ]);
            }

            /*
             * Assistant/system messages
             */
            return Message::create($data);
        } catch (\Throwable $th) {
            Log::error('Message repository send failed.', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
                'data' => $data,
            ]);

            throw $th;
        }
    }
}
