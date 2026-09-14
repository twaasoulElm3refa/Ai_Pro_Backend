<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelsMessage extends Model
{
    protected $table = 'models_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function conversation()
    {
        return $this->belongsTo(ModelsConverstaions::class, 'models_conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
