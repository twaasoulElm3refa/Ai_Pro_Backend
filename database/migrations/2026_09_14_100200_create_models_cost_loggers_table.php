<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('models_cost_loggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('models_conversation_id')->constrained('models_conversations')->restrictOnDelete();
            $table->foreignId('model_id')->constrained('main_free_ai_models')->restrictOnDelete();
            $table->foreignId('assistant_message_id')->unique()->constrained('models_messages')->restrictOnDelete();
            $table->string('provider', 64);
            $table->string('provider_model_id');
            $table->string('request_id', 64)->unique();
            $table->unsignedBigInteger('input_tokens');
            $table->unsignedBigInteger('output_tokens');
            $table->unsignedBigInteger('reasoning_tokens');
            $table->unsignedBigInteger('total_tokens');
            $table->decimal('input_cost', 14, 8)->default(0);
            $table->decimal('output_cost', 14, 8)->default(0);
            $table->decimal('total_cost', 14, 8)->default(0);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['models_conversation_id', 'created_at'], 'models_cost_conversation_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('models_cost_loggers');
    }
};
