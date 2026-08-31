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
        Schema::create('ai_agent_checkpoints', function (Blueprint $table) {
            $table->id();

            $table->string('checkpoint_uuid', 100);

            // run_id: NOT NULL + ON DELETE CASCADE
            $table->foreignIdFor(AiAgentsRuns::class, 'run_id')
                ->constrained()
                ->cascadeOnDelete();

            // step_id: NULL + ON DELETE SET NULL
            $table->foreignIdFor(AiAgentSteps::class, 'step_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('node_name', 100);

            $table->integer('iteration')
                ->default(0);

            $table->string('status', 30)
                ->default('completed');

            $table->json('state_data');

            $table->json('metadata')
                ->nullable();

            $table->dateTime('created_at')
                ->useCurrent();

            // Indexes
            $table->unique(
                'checkpoint_uuid',
                'uq_ai_agent_checkpoint_uuid'
            );

            $table->index(
                ['run_id', 'id'],
                'idx_ai_agent_checkpoint_run'
            );

            $table->index(
                'step_id',
                'idx_ai_agent_checkpoint_step'
            );

            $table->index(
                ['run_id', 'node_name', 'iteration'],
                'idx_ai_agent_checkpoint_node'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_checkpoints');
    }
};
