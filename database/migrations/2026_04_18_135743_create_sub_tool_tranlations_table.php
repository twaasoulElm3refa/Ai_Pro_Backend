<?php

use App\Models\SubTools;
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
        Schema::create('sub_tool_tranlations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SubTools::class ,'sub_tool_id')->constrained()->cascadeOnDelete();
            $table->string('locale');

            $table->string('name')->nullable();
            $table->string('prompt_placeholder')->nullable();
            $table->text('description')->nullable();

            $table->string('meta_name')->nullable();
            $table->text('meta_description')->nullable();

            $table->timestamps();
            $table->unique(['sub_tool_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_tool_tranlations');
    }
};
