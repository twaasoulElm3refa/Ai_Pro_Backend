<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('models_conversations', function (Blueprint $table) {
            $table->string('catalog_operation', 64)->nullable()->after('selected_model_source');
            $table->index(
                ['user_id', 'model_id', 'catalog_operation', 'is_archived', 'created_at'],
                'models_conversations_operation_sidebar_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('models_conversations', function (Blueprint $table) {
            $table->dropIndex('models_conversations_operation_sidebar_index');
            $table->dropColumn('catalog_operation');
        });
    }
};
