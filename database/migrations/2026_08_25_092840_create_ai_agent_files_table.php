<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_files', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('run_id');

            $table->unsignedBigInteger('step_id')
                ->nullable();

            $table->enum('role', [
                'input',
                'reference',
                'temporary',
                'output',
            ]);

            $table->string('filename', 255);

            $table->text('storage_path');

            $table->text('public_url')
                ->nullable();

            $table->string('content_type', 100)
                ->nullable();

            $table->unsignedBigInteger('size_bytes')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->dateTime('created_at')
                ->useCurrent();


            /*
             * Indexes
             */

            $table->index(
                'run_id',
                'idx_agent_files_run'
            );

            $table->index(
                'step_id',
                'idx_agent_files_step'
            );
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('ai_agent_files');
    }
};
