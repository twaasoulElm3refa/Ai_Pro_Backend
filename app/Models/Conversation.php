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

    public function subTool()
    {
        return $this->sub_tool();
    }

    public function message()
    {
        return $this->hasMany(Message::class,'conversation_id');
    }

    public function messages()
    {
        return $this->message();
    }

    public function firstUserMessage()
    {
        return $this->hasOne(Message::class, 'conversation_id')
            ->where('messages.role', 'user')
            ->oldestOfMany('id');
    }

    public function cost()
    {
        return $this->hasMany(CostLogger::class,'conversation_id');
    }

    public function firstMessage()
    {
        return $this->firstUserMessage();
    }

    public function generatedImages()
    {
        return $this->hasMany(GeneratedImage::class);
    }
}
