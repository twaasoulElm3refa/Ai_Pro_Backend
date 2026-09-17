<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('models_message_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('models_message_id')->constrained('models_messages')->cascadeOnDelete();
            $table->string('file_id', 128)->unique();
            $table->string('filename');
            $table->string('content_type', 150);
            $table->string('download_url', 2048);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['models_message_id', 'content_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('models_message_files');
    }
};
