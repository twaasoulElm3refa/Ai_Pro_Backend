<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Message extends Model
{
    use SoftDeletes;

    protected $table = 'messages';

    protected $guarded = [];

    protected $casts = [
        'is_error' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Message $message) {
            $message->role = $message->role ?: 'user';
            $message->content = trim((string) $message->content);

            if ($message->content === '') {
                Log::warning('Blocked empty message creation.');

                return false;
            }

            /*
             * الحماية دي على user messages فقط.
             */
            if ($message->role !== 'user') {
                return true;
            }

            /*
             * أي user message لازم يكون لها idempotency_key.
             * لو أي كود قديم حاول يدخلها NULL، نولّد key هنا.
             */
            if (empty($message->idempotency_key)) {
                $message->idempotency_key = (string) Str::uuid();

                Log::warning('Generated missing idempotency_key for user message.', [
                    'conversation_id' => $message->conversation_id,
                    'content_length' => mb_strlen($message->content),
                ]);
            }

            /*
             * حماية نهائية:
             * لو نفس المحتوى اتسجل في نفس المحادثة خلال آخر 30 ثانية، امنع الحفظ.
             */
            $duplicateExists = self::query()
                ->where('conversation_id', $message->conversation_id)
                ->where('role', 'user')
                ->where('content', $message->content)
                ->where('created_at', '>=', now()->subSeconds(30))
                ->exists();

            if ($duplicateExists) {
                Log::warning('Blocked duplicate user message creation.', [
                    'conversation_id' => $message->conversation_id,
                    'idempotency_key' => $message->idempotency_key,
                    'content_length' => mb_strlen($message->content),
                ]);

                return false;
            }

            return true;
        });
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
