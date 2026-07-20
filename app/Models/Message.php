<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Message extends Model
{
    use SoftDeletes;

    private const CHAT4_SUB_TOOL_IDS = [17, 18, 19, 20];

    protected $table = 'messages';

    protected $guarded = [];

    protected $casts = [
        'is_error' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (Message $message): void {
            if ($message->role !== 'user') {
                return;
            }

            $conversation = $message->conversation()->first();

            if (! $conversation || ! in_array((int) $conversation->sub_tool_id, self::CHAT4_SUB_TOOL_IDS, true)) {
                return;
            }

            if (! Schema::hasColumn('conversations', 'title')) {
                return;
            }

            if (trim((string) ($conversation->title ?? '')) !== '') {
                return;
            }

            $hasEarlierUserMessage = static::where('conversation_id', $conversation->id)
                ->where('role', 'user')
                ->where('id', '<', $message->id)
                ->exists();

            if ($hasEarlierUserMessage) {
                return;
            }

            $title = self::conversationTitleFromMessage($message->content);

            if ($title !== '') {
                $conversation->updateQuietly(['title' => $title]);
            }
        });
    }

    private static function conversationTitleFromMessage(?string $content): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $content)) ?? '');

        if ($clean === '') {
            return '';
        }

        $words = preg_split('/\s+/u', $clean, 5, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_slice($words, 0, 4));
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
