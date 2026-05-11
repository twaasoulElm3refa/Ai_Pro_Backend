<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostLogger extends Model
{
    protected $table = 'cost_loggers';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'sub_tool_id',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'input_cost',
        'output_cost',
        'web_search_cost',
        'total_cost',
        'currency',
        'provider_request_id',
        'model_key',
    ];

    protected $casts = [
        'input_cost' => 'decimal:8',
        'output_cost' => 'decimal:8',
        'web_search_cost' => 'decimal:8',
        'total_cost' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subTool()
    {
        return $this->belongsTo(SubTools::class, 'sub_tool_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
