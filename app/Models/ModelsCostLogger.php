<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelsCostLogger extends Model
{
    protected $table = 'models_cost_loggers';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function conversation()
    {
        return $this->belongsTo(ModelsConverstaions::class, 'models_conversation_id');
    }

    public function assistantMessage()
    {
        return $this->belongsTo(ModelsMessage::class, 'assistant_message_id');
    }

    public function walletTransaction()
    {
        return $this->hasOne(WalletTransaction::class, 'models_cost_logger_id');
    }
}
