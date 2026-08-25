<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_tools', function (Blueprint $table) {
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();

            $table->text('thumbnail_url')->nullable();
            $table->text('preview_url')->nullable();

            $table->longText('prompt_template')->nullable();
            $table->longText('negative_prompt')->nullable();

            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->json('allowed_model_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sub_tools', function (Blueprint $table) {
            $table->dropColumn([
                'name_ar',
                'name_en',
                'thumbnail_url',
                'preview_url',
                'prompt_template',
                'negative_prompt',
                'input_schema',
                'output_schema',
                'allowed_model_ids',
            ]);
        });
    }
};
