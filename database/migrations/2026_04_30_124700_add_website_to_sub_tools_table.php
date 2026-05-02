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
        Schema::table('sub_tools', function (Blueprint $table) {
            if (! Schema::hasColumn('sub_tools', 'website')) {
                $table->string('website')->nullable()->after('meta_description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_tools', function (Blueprint $table) {
            if (Schema::hasColumn('sub_tools', 'website')) {
                $table->dropColumn('website');
            }
        });
    }
};
