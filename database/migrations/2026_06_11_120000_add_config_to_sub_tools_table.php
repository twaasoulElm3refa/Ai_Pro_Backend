<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_tools', function (Blueprint $table) {
            if (! Schema::hasColumn('sub_tools', 'config')) {
                $table->json('config')->nullable()->after('endpoint');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sub_tools', function (Blueprint $table) {
            if (Schema::hasColumn('sub_tools', 'config')) {
                $table->dropColumn('config');
            }
        });
    }
};
