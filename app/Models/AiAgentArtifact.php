<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiAgentArtifact extends Model
{
    use SoftDeletes;

    protected $table = 'ai_agent_artifacts';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'run_id' => 'integer',
        'step_id' => 'integer',
        'tool_call_id' => 'integer',
        'size_bytes' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'deleted_at' => 'datetime',
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

    public function toolCall(): BelongsTo
    {
        return $this->belongsTo(
            AiAgentToolCall::class,
            'tool_call_id'
        );
    }
}
