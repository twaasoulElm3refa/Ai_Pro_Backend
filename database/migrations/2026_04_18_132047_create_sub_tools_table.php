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
        Schema::create('sub_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(MainTools::class,'main_tool_id')->constrained()->cascadeOnDelete();

            $table->string('name')->unique();
            $table->string('meta_name')->nullable();

            $table->string('description')->nullable();
            $table->text('meta_description')->nullable();

            $table->string('image')->nullable();
            $table->string('slug')->unique();
            $table->string('prompt_placeholder')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_tools');
    }
};
