<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_loggers', function (Blueprint $table) {
            $table->decimal('total_cost', 12, 6)->default(0)->change();
            $table->decimal('input_cost', 12, 6)->default(0)->change();
            $table->decimal('output_cost', 12, 6)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('cost_loggers', function (Blueprint $table) {
            $table->decimal('total_cost', 8, 2)->default(0)->change();
            $table->decimal('input_cost', 8, 2)->default(0)->change();
            $table->decimal('output_cost', 8, 2)->default(0)->change();
        });
    }
};
