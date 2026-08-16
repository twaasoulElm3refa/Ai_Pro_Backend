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
        Schema::create('ai_main_model_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(AiMainModel::class, 'tool_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('locale');

            $table->text('name');
            $table->longText('description')->nullable();

            $table->text('meta_title')->nullable();
            $table->longText('meta_description')->nullable();

            $table->string('seo_keywords')->nullable();
            $table->timestamps();
            $table->unique(['tool_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_main_model_translations');
    }
};
