<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('models_conversations', function (Blueprint $table) {
            $table->renameColumn('selected_model_catalog_id', 'selected_model_id');
            $table->renameColumn('selected_provider_model_id', 'provider_model_id');
        });

        Schema::table('models_conversations', function (Blueprint $table) {
            $table->string('title')->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('tool_key', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('models_conversations', function (Blueprint $table) {
            $table->dropColumn(['title', 'provider', 'tool_key']);
            $table->renameColumn('selected_model_id', 'selected_model_catalog_id');
            $table->renameColumn('provider_model_id', 'selected_provider_model_id');
        });
    }
};
