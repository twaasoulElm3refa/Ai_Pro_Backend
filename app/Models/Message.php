<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $table = 'messages';

    protected $guarded = [];

    protected $casts = [
        'is_error' => 'boolean',
    ];


    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
