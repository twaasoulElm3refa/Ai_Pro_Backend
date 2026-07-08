<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_generated_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('file_id')->unique();
            $table->foreignIdFor(User::class, 'user_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Conversation::class, 'conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Message::class, 'message_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('conversation_uuid')->index();
            $table->unsignedBigInteger('sub_tool_id')->default(19)->index();
            $table->string('filename');
            $table->string('path');
            $table->string('disk')->default('local');
            $table->string('content_type')->nullable();
            $table->string('output_format', 20)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'file_id']);
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_generated_files');
    }
};
