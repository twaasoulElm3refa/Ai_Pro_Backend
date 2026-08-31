<?php

use App\Models\AiAgentsRuns;
use App\Models\AiAgentSteps;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_tool_calls', function (Blueprint $table) {
            $table->id();

            $table->string('call_uuid', 100);

            // run_id: NOT NULL + ON DELETE CASCADE
            $table->foreignIdFor(AiAgentsRuns::class, 'run_id')
                ->constrained()
                ->cascadeOnDelete();

            // step_id: NULL + ON DELETE SET NULL
            $table->foreignIdFor(AiAgentSteps::class, 'step_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->integer('plan_step_index')
                ->nullable();

            $table->string('tool_key', 100);

            $table->string('operation', 100)
                ->nullable();

            $table->unsignedBigInteger('selected_model_id')
                ->nullable();

            $table->string('status', 30)
                ->default('pending');

            $table->json('request_data')
                ->nullable();

            $table->json('response_data')
                ->nullable();

            $table->json('usage_data')
                ->nullable();

            $table->json('cost_data')
                ->nullable();

            $table->text('error_message')
                ->nullable();

            $table->dateTime('started_at')
                ->nullable();

            $table->dateTime('completed_at')
                ->nullable();

            $table->dateTime('created_at')
                ->useCurrent();

            // Indexes
            $table->unique(
                'call_uuid',
                'uq_ai_agent_tool_call_uuid'
            );

            $table->index(
                ['run_id', 'id'],
                'idx_ai_agent_tool_call_run'
            );

            $table->index(
                'step_id',
                'idx_ai_agent_tool_call_step'
            );

            $table->index(
                'status',
                'idx_ai_agent_tool_call_status'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_tool_calls');
    }
};
