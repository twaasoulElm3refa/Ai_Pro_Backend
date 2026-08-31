<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {

            $table->id();

            $table->string('slug', 100)->unique();

            $table->string('name_ar', 255);

            $table->string('name_en', 255)
                ->nullable();

            $table->text('description')
                ->nullable();

            $table->longText('system_prompt');

            $table->json('allowed_tools');

            $table->json('allowed_model_ids')
                ->nullable();

            $table->json('input_schema')
                ->nullable();

            $table->json('output_schema')
                ->nullable();

            $table->json('default_parameters')
                ->nullable();

            $table->integer('max_steps')
                ->default(8);

            $table->integer('max_files')
                ->default(5);

            $table->integer('max_execution_seconds')
                ->default(300);

            $table->decimal('default_budget_usd', 12, 6)
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->boolean('is_recommended')
                ->default(false);

            $table->integer('sort_order')
                ->default(0);

            $table->dateTime('created_at')
                ->useCurrent();

            $table->dateTime('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->softDeletes();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
