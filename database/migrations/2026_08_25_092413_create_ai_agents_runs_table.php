<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_runs', function (Blueprint $table) {

            $table->id();

            $table->string('run_uuid', 100)
                ->unique();

            $table->unsignedBigInteger('agent_id');

            $table->unsignedBigInteger('user_id');

            $table->unsignedBigInteger('model_id');

            $table->unsignedBigInteger('selected_model_id')
                ->nullable();

            $table->string('conversation_uuid', 255);

            $table->longText('user_message');

            $table->enum('status', [
                'queued',
                'planning',
                'running',
                'completed',
                'failed',
                'cancelled',
            ])->default('queued');

            $table->json('input_state')
                ->nullable();

            $table->longText('final_response')
                ->nullable();

            $table->json('output_files')
                ->nullable();

            $table->json('usage_data')
                ->nullable();

            $table->json('cost_data')
                ->nullable();

            $table->text('error_message')
                ->nullable();

            $table->integer('total_steps')
                ->default(0);

            $table->integer('completed_steps')
                ->default(0);

            $table->dateTime('started_at')
                ->nullable();

            $table->dateTime('completed_at')
                ->nullable();

            $table->dateTime('created_at')
                ->useCurrent();

            $table->dateTime('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();


            /*
             * Indexes
             */

            $table->index(
                ['user_id', 'created_at'],
                'idx_agent_runs_user'
            );

            $table->index(
                'status',
                'idx_agent_runs_status'
            );

            $table->index(
                'conversation_uuid',
                'idx_agent_runs_conversation'
            );
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('ai_agent_runs');
    }
};
