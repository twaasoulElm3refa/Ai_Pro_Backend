<?php

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
        Schema::create('main_free_ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('meta_name')->nullable();

            $table->text('description')->nullable();
            $table->text('meta_description')->nullable();

            $table->string('image')->nullable();
            $table->string('slug')->unique();

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
        Schema::dropIfExists('main_free_ai_models');
    }
};
