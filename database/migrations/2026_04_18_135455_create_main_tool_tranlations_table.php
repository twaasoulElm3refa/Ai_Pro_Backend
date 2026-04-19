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
            $table->foreignIdFor(MainTools::class ,'main_tool_id')->constrained()->cascadeOnDelete();
            $table->string('locale');
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['main_tool_id', 'locale']);
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
