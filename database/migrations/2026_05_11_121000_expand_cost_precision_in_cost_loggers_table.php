<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_loggers', function (Blueprint $table) {
            $table->decimal('input_cost', 14, 8)->default(0)->change();
            $table->decimal('output_cost', 14, 8)->default(0)->change();
            $table->decimal('total_cost', 14, 8)->default(0)->change();

            if (Schema::hasColumn('cost_loggers', 'web_search_cost')) {
                $table->decimal('web_search_cost', 14, 8)->default(0)->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cost_loggers', function (Blueprint $table) {
            $table->decimal('input_cost', 12, 6)->default(0)->change();
            $table->decimal('output_cost', 12, 6)->default(0)->change();
            $table->decimal('total_cost', 12, 6)->default(0)->change();

            if (Schema::hasColumn('cost_loggers', 'web_search_cost')) {
                $table->decimal('web_search_cost', 12, 6)->default(0)->change();
            }
        });
    }
};
