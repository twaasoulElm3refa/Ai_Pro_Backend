<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResumeGeneratedFile extends Model
{
    protected $fillable = [
        'file_id',
        'user_id',
        'conversation_id',
        'message_id',
        'conversation_uuid',
        'sub_tool_id',
        'filename',
        'path',
        'disk',
        'content_type',
        'output_format',
        'size',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}
