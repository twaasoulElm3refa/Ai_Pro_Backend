<?php

use App\Models\AiAgentsRuns;
use App\Models\AiAgentSteps;
use App\Models\AiAgentToolCall;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_artifacts', function (Blueprint $table) {
            $table->id();

            $table->string('artifact_uuid', 100);

            $table->foreignIdFor(AiAgentsRuns::class, 'run_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignIdFor(AiAgentSteps::class, 'step_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignIdFor(AiAgentToolCall::class, 'tool_call_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('tool_key', 100)
                ->nullable();

            $table->string('artifact_type', 50);

            $table->string('role', 30)
                ->default('output');

            $table->string('filename', 255);

            $table->text('storage_path');

            $table->string('content_type', 150)
                ->nullable();

            $table->unsignedBigInteger('size_bytes')
                ->nullable();

            $table->char('checksum_sha256', 64)
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->dateTime('created_at')
                ->useCurrent();

            $table->dateTime('deleted_at')
                ->nullable();

            // Indexes
            $table->unique(
                'artifact_uuid',
                'uq_ai_agent_artifact_uuid'
            );

            $table->index(
                ['run_id', 'created_at'],
                'idx_ai_agent_artifact_run'
            );

            $table->index(
                'step_id',
                'idx_ai_agent_artifact_step'
            );

            $table->index(
                'tool_call_id',
                'idx_ai_agent_artifact_tool_call'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_artifacts');
    }
};
