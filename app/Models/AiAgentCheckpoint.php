<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentCheckpoint extends Model
{
    protected $table = 'ai_agent_checkpoints';

    /**
     * الجدول يحتوي على created_at فقط
     * ولا يحتوي على updated_at.
     */
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'run_id' => 'integer',
        'step_id' => 'integer',
        'iteration' => 'integer',
        'state_data' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * الـ Run المرتبط بالـ checkpoint.
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(
            AiAgentsRuns::class,
            'run_id'
        );
    }

    /**
     * الـ Step المرتبط بالـ checkpoint.
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(
            AiAgentSteps::class,
            'step_id'
        );
    }
}
