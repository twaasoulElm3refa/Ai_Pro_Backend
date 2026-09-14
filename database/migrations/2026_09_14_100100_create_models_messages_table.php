<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('models_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('models_conversation_id')->constrained('models_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->longText('content');
            $table->string('request_id', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['models_conversation_id', 'id']);
            $table->unique(['models_conversation_id', 'request_id', 'role'], 'models_messages_request_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('models_messages');
    }
};
