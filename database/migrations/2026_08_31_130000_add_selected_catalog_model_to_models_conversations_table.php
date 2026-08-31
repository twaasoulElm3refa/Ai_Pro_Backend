<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('models_conversations', function (Blueprint $table) {
            $table->string('selected_model_source', 64)->nullable()->after('model_id');
            $table->unsignedBigInteger('selected_model_catalog_id')->nullable()->after('selected_model_source');
            $table->string('selected_provider_model_id')->nullable()->after('selected_model_catalog_id');
            $table->string('selected_model_name')->nullable()->after('selected_provider_model_id');

            $table->index(
                ['user_id', 'model_id', 'is_archived', 'created_at'],
                'models_conversations_sidebar_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('models_conversations', function (Blueprint $table) {
            $table->dropIndex('models_conversations_sidebar_index');
            $table->dropColumn([
                'selected_model_source',
                'selected_model_catalog_id',
                'selected_provider_model_id',
                'selected_model_name',
            ]);
        });
    }
};
