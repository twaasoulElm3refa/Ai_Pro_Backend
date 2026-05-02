<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable()->after('role');
            $table->foreignId('reply_to_message_id')
                ->nullable()
                ->after('idempotency_key')
                ->constrained('messages')
                ->nullOnDelete();

            $table->unique(
                ['conversation_id', 'role', 'idempotency_key'],
                'messages_conversation_role_idempotency_unique'
            );
            $table->unique('reply_to_message_id', 'messages_reply_to_message_unique');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_conversation_role_idempotency_unique');
            $table->dropUnique('messages_reply_to_message_unique');
            $table->dropConstrainedForeignId('reply_to_message_id');
            $table->dropColumn('idempotency_key');
        });
    }
};
