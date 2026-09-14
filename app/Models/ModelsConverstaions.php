<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModelsConverstaions extends Model
{
    use SoftDeletes;

    protected $table = 'models_conversations';

    protected $guarded = [];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_archived' => 'boolean',
        'selected_model_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(MainFreeAiModels::class, 'model_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ModelsMessage::class, 'models_conversation_id');
    }

    public function costLoggers(): HasMany
    {
        return $this->hasMany(ModelsCostLogger::class, 'models_conversation_id');
    }
}
