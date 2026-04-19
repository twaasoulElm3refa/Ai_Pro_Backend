<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;
    protected $table = 'conversations';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function sub_tool()
    {
        return $this->belongsTo(SubTools::class,'sub_tool_id');
    }

    public function message()
    {
        return $this->hasMany(Message::class,'conversation_id');
    }
}
