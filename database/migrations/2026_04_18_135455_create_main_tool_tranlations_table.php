<?php

use App\Models\MainTools;
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
        Schema::create('main_tool_tranlations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(MainTools::class)->constrained()->cascadeOnDelete();
            $table->string('locale');

            $table->text('name');
            $table->longText('description')->nullable();

            $table->text('meta_title')->nullable();
            $table->longText('meta_description')->nullable();

            $table->string('seo_keywords')->nullable();
            $table->timestamps();
            $table->unique(['main_tools_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('main_tool_tranlations');
    }
};
