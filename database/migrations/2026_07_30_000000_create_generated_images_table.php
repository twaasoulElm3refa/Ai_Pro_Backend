<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sub_tool_id')->index();
            $table->string('source_file_id')->nullable()->index();
            $table->string('filename');
            $table->string('path');
            $table->string('disk', 50)->default('local');
            $table->string('content_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_images');
    }
};
