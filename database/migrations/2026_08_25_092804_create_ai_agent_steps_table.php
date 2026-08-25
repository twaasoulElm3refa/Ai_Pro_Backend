<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_steps', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('run_id');

            $table->integer('step_number');

            $table->string('step_key', 100)
                ->nullable();

            $table->string('title', 255)
                ->nullable();

            $table->string('tool_key', 100)
                ->nullable();

            $table->string('operation', 100)
                ->nullable();

            $table->unsignedBigInteger('selected_model_id')
                ->nullable();

            $table->enum('status', [
                'pending',
                'running',
                'completed',
                'failed',
                'skipped',
            ])->default('pending');

            $table->json('tool_input')
                ->nullable();

            $table->json('tool_output')
                ->nullable();

            $table->json('output_files')
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


            /*
             * Indexes
             */

            $table->index(
                ['run_id', 'step_number'],
                'idx_agent_steps_run'
            );

            $table->index(
                'status',
                'idx_agent_steps_status'
            );
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('ai_agent_steps');
    }
};
