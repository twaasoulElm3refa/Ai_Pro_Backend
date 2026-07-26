<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'amount_minor')) {
                $table->unsignedBigInteger('amount_minor')
                    ->nullable()
                    ->after('amount');
            }

            if (! Schema::hasColumn('payments', 'expected_points')) {
                $table->unsignedBigInteger('expected_points')
                    ->nullable()
                    ->after('amount_minor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'expected_points')) {
                $table->dropColumn('expected_points');
            }

            if (Schema::hasColumn('payments', 'amount_minor')) {
                $table->dropColumn('amount_minor');
            }
        });
    }
};
