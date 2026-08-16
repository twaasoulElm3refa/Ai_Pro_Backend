<?php

use App\Models\AiMainModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('main_free_ai_models', function (Blueprint $table) {
            $table->foreignIdFor(AiMainModel::class, 'main_tool_id')->default(1)->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('main_free_ai_models', function (Blueprint $table) {
            $table->dropColumn('main_tool_id');
        });
    }
};
