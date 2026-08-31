<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agent_runs', function (Blueprint $table) {
            $table->json('state_data')->nullable()->after('error_message');

            $table->string('current_node', 100)
                ->nullable()
                ->after('state_data');

            $table->integer('iteration')
                ->default(0)
                ->after('current_node');

            $table->integer('max_iterations')
                ->default(2)
                ->after('iteration');
        });
    }

    public function down(): void
    {
        Schema::table('ai_agent_runs', function (Blueprint $table) {
            $table->dropColumn([
                'state_data',
                'current_node',
                'iteration',
                'max_iterations',
            ]);
        });
    }
};
