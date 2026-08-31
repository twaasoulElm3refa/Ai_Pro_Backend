<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->unsignedBigInteger('evaluator_model_id')
                ->nullable()
                ->after('allowed_model_ids');

            $table->foreign(
                'evaluator_model_id',
                'fk_ai_agents_evaluator_model'
            )
                ->references('id')
                ->on('ai_models')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropForeign('fk_ai_agents_evaluator_model');
            $table->dropColumn('evaluator_model_id');
        });
    }
};
