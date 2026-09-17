<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function files(): HasMany
    {
        return $this->hasMany(ModelsMessageFile::class, 'models_message_id');
    }
}
