<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentToolCall extends Model
{
    protected $table = 'ai_agent_tool_calls';

    /**
     * الجدول يحتوي على created_at فقط.
     */
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'run_id' => 'integer',
        'step_id' => 'integer',
        'plan_step_index' => 'integer',
        'selected_model_id' => 'integer',

        'request_data' => 'array',
        'response_data' => 'array',
        'usage_data' => 'array',
        'cost_data' => 'array',

        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(
            AiAgentsRuns::class,
            'run_id'
        );
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(
            AiAgentSteps::class,
            'step_id'
        );
    }
}
