<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneratedImage extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'conversation_id',
        'message_id',
        'sub_tool_id',
        'source_file_id',
        'filename',
        'path',
        'disk',
        'content_type',
        'size_bytes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size_bytes' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
